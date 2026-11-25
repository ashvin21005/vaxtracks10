<?php

namespace App\Http\Controllers;

use App\Models\Child;
use App\Models\HealthStation;
use App\Models\ImmunizationRecord;
use App\Models\SmsNotification;
use App\Models\VaccinationSchedule;
use App\Models\Vaccine;
use App\Models\VaccineInventory;
use App\Services\EpiScheduleService;
use App\Services\SkySmsService;
use App\Services\VaccineInventoryService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class OperationsWorkflowController extends Controller
{
    public function immunizations(Request $request)
    {
        $recordsQuery = ImmunizationRecord::with(['child.guardian', 'recorder'])->latest('administered_at');
        $this->scopeRecordsToUser($recordsQuery);
        $summaryQuery = clone $recordsQuery;
        $search = trim((string) $request->query('search', ''));
        if ($search !== '') {
            $recordsQuery->where(function ($query) use ($search) {
                $query->where('vaccine', 'like', "%{$search}%")
                    ->orWhereHas('child', fn ($children) => $children->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%"));
            });
        }
        $perPage = (int) $request->query('per_page', 15);
        if (! in_array($perPage, [10, 15, 25, 50], true)) {
            $perPage = 15;
        }

        return view('operations.immunizations', [
            'records' => $recordsQuery->paginate($perPage)->withQueryString(),
            'perPage' => $perPage,
            'search' => $search,
            'children' => $this->childrenForCurrentUser()->orderBy('last_name')->get(),
            'vaccines' => $this->vaccineNames(),
            'vaccineIds' => Vaccine::whereIn('name', $this->vaccineNames())->pluck('id', 'name'),
            'summary' => [
                'this_month' => (clone $summaryQuery)->whereMonth('administered_at', now()->month)->whereYear('administered_at', now()->year)->count(),
                'children' => (clone $summaryQuery)->distinct('child_id')->count('child_id'),
                'latest' => (clone $summaryQuery)->max('administered_at'),
            ],
        ]);
    }

    public function createImmunization()
    {
        return redirect()->route('immunizations.index', ['record' => 1]);
    }

    public function storeImmunization(Request $request)
    {
        $data = $request->validate($this->immunizationRules());
        $this->ensureChildIsInScope((int) $data['child_id']);
        $vaccine = Vaccine::where('name', $data['vaccine'])->where('is_active', true)->firstOrFail();

        $child = DB::transaction(function () use ($request, $data, $vaccine) {
            $child = Child::findOrFail($data['child_id']);
            abort_unless($child->health_station_id, 403);
            $validation = app(EpiScheduleService::class)->validationForLiveDose(
                $child,
                $vaccine,
                (int) $data['dose_number'],
                $data['administered_at']
            );
            app(VaccineInventoryService::class)->deductDose($child->health_station_id, $vaccine);

            ImmunizationRecord::create($data + [
                'vaccine_id' => $vaccine->id,
                'recorded_by' => $request->user()->id,
                'validation_status' => $validation['status'],
                'status' => $validation['status'] === 'INVALID' ? 'INVALID' : 'COMPLETED',
            ]);

            return $child;
        });
        app(EpiScheduleService::class)->syncChild($child->fresh());

        return redirect()->route('immunizations.index')->with('success', 'Immunization record saved.');
    }

    public function editImmunization(ImmunizationRecord $record)
    {
        $this->ensureChildIsInScope($record->child_id);
        $vaccines = $this->vaccineNames(false, $record->vaccine);

        return view('operations.record-form', [
            'record' => $record,
            'children' => $this->childrenForCurrentUser()->orderBy('last_name')->get(),
            'vaccines' => $vaccines,
            'vaccineIds' => Vaccine::whereIn('name', $vaccines)->pluck('id', 'name'),
        ]);
    }

    public function updateImmunization(Request $request, ImmunizationRecord $record)
    {
        $this->ensureChildIsInScope($record->child_id);
        $sourceChildId = (int) $record->child_id;
        $data = $request->validate($this->immunizationRules($record));
        $this->ensureChildIsInScope((int) $data['child_id']);
        $vaccine = Vaccine::where('name', $data['vaccine'])->firstOrFail();
        $targetChild = Child::findOrFail($data['child_id']);
        $validation = app(EpiScheduleService::class)->validationForLiveDose(
            $targetChild,
            $vaccine,
            (int) $data['dose_number'],
            $data['administered_at'],
            $record
        );
        DB::transaction(function () use ($record, $data, $vaccine) {
            $oldDose = [(int) $record->child_id, $record->vaccine, (int) $record->dose_number];
            $record->update($data + [
                'vaccine_id' => $vaccine->id,
                'date_precision' => 'DAY',
                'record_type' => 'LIVE',
                'validation_status' => 'VALID',
                'status' => 'COMPLETED',
            ]);

            if ($oldDose !== [(int) $record->child_id, $record->vaccine, (int) $record->dose_number]) {
                VaccinationSchedule::where('child_id', $oldDose[0])
                    ->where('vaccine', $oldDose[1])
                    ->where('dose_number', $oldDose[2])
                    ->update(['status' => 'UPCOMING']);
            }
            VaccinationSchedule::where('child_id', $record->child_id)
                ->where('vaccine', $record->vaccine)
                ->where('dose_number', $record->dose_number)
                ->update(['status' => 'COMPLETED']);
        });
        $record->update([
            'validation_status' => $validation['status'],
            'status' => $validation['status'] === 'INVALID' ? 'INVALID' : 'COMPLETED',
        ]);
        app(EpiScheduleService::class)->syncChild($targetChild->fresh());
        if ($sourceChildId !== $targetChild->id) {
            app(EpiScheduleService::class)->syncChild(Child::findOrFail($sourceChildId));
        }

        return redirect()->route('immunizations.index')->with('success', 'Immunization record updated.');
    }

    public function destroyImmunization(Request $request, ImmunizationRecord $record)
    {
        $this->ensureChildIsInScope($record->child_id);

        $child = $record->child;
        DB::transaction(function () use ($record) {
            $record->delete();
        });
        app(EpiScheduleService::class)->syncChild($child);

        return redirect()->route('immunizations.index')->with('success', 'Immunization record deleted.');
    }

    public function schedule(Request $request)
    {
        $query = VaccinationSchedule::with(['child.guardian', 'vaccineDefinition'])
            ->latest('scheduled_date')
            ->orderBy('child_id')
            ->orderBy('vaccine');
        $this->scopeSchedulesToUser($query);
        $search = trim((string) $request->query('search', ''));
        $status = $request->query('status');
        $vaccine = $request->query('vaccine');
        if ($search !== '') {
            $query->where(function ($schedules) use ($search) {
                $schedules->where('vaccine', 'like', "%{$search}%")
                    ->orWhereHas('child', fn ($children) => $children->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%"));
            });
        }
        if ($vaccine) {
            $query->where('vaccine', $vaccine);
        }
        if ($request->query('date')) {
            $query->whereDate('scheduled_date', $request->query('date'));
        }
        $allSchedules = $query->get();
        $status = match ($status) {
            'UPCOMING' => 'PENDING',
            'DELAYED', 'MISSED' => 'OVERDUE',
            default => $status,
        };
        if (in_array($status, ['PENDING', 'DUE', 'OVERDUE', 'COMPLETED', 'INVALID', 'FOR REVIEW'], true)) {
            $allSchedules = $allSchedules->filter(fn ($schedule) => $schedule->display_status === $status)->values();
        }
        $allVisits = $allSchedules->groupBy(fn ($schedule) => $schedule->child_id.'|'.$schedule->scheduled_date->toDateString())->values();
        $perPage = 15;
        $page = LengthAwarePaginator::resolveCurrentPage();
        $visits = new LengthAwarePaginator(
            $allVisits->forPage($page, $perPage)->values(),
            $allVisits->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return view('operations.schedule', [
            'visits' => $visits,
            'totalDoses' => $allSchedules->count(),
            'search' => $search,
            'status' => $status,
            'vaccine' => $vaccine,
            'vaccines' => $this->vaccineNames(),
            'children' => $this->childrenForCurrentUser()->orderBy('last_name')->get(),
            'summary' => $this->scheduleSummary(),
        ]);
    }

    public function updateScheduleStatus(Request $request, VaccinationSchedule $schedule)
    {
        $this->ensureChildIsInScope($schedule->child_id);
        $data = $request->validate([
            'status' => ['sometimes', 'required', Rule::in(['UPCOMING', 'MISSED'])],
            'scheduled_date' => ['sometimes', 'required', 'date'],
        ]);
        if ($data === []) {
            throw ValidationException::withMessages(['scheduled_date' => 'Provide a new schedule date or status.']);
        }
        if (isset($data['scheduled_date']) && ! $schedule->generated_by_protocol) {
            $data['due_date'] = $data['scheduled_date'];
        }
        $schedule->update($data);
        if (array_key_exists('scheduled_date', $data)) {
            SmsNotification::where('vaccination_schedule_id', $schedule->id)
                ->where('status', 'PENDING')
                ->update(['scheduled_date' => $data['scheduled_date']]);
        }

        return redirect()->route('schedule.index')->with('success', 'Vaccination schedule updated.');
    }

    public function destroySchedule(Request $request, VaccinationSchedule $schedule)
    {
        $this->ensureChildIsInScope($schedule->child_id);

        DB::transaction(function () use ($schedule) {
            SmsNotification::where('vaccination_schedule_id', $schedule->id)
                ->where('status', 'PENDING')
                ->delete();
            $schedule->delete();
        });

        return redirect()->route('schedule.index')->with('success', 'Scheduled dose removed. Sent reminder history was retained.');
    }

    public function inventory(Request $request)
    {
        $query = VaccineInventory::with(['healthStation', 'vaccineDefinition'])->orderBy('vaccine');
        $this->scopeInventoryToUser($query, $request->user());
        $search = trim((string) $request->query('search', ''));
        $vaccine = $request->query('vaccine');
        if ($search !== '') {
            $query->where('vaccine', 'like', "%{$search}%");
        }
        if ($vaccine && $this->vaccineNames()->contains($vaccine)) {
            $query->where('vaccine', $vaccine);
        }
        $summaryQuery = clone $query;

        return view('operations.inventory', [
            'stocks' => $query->paginate(15)->withQueryString(),
            'search' => $search,
            'vaccine' => $vaccine,
            'stations' => HealthStation::orderBy('name')->get(),
            'vaccines' => $this->vaccineNames(),
            'summary' => [
                'types' => (clone $summaryQuery)->count(),
                'low' => (clone $summaryQuery)->whereColumn('quantity', '<=', 'reorder_level')->where('quantity', '>', 0)->count(),
                'empty' => (clone $summaryQuery)->where('quantity', 0)->count(),
            ],
        ]);
    }

    public function storeInventory(Request $request)
    {
        abort_unless($request->user()->isCho(), 403);
        $data = $request->validate([
            'vaccine' => ['required', Rule::in($this->vaccineNames()->all())],
            'quantity' => ['required', 'integer', 'min:1', 'max:100000'],
            'reorder_level' => ['required', 'integer', 'min:0', 'max:100000'],
            'health_station_id' => ['nullable', 'exists:health_stations,id'],
        ]);
        $stationId = $data['health_station_id'] ?? null;
        $vaccine = Vaccine::where('name', $data['vaccine'])->where('is_active', true)->firstOrFail();
        DB::transaction(function () use ($data, $vaccine, $stationId) {
            $stockQuery = VaccineInventory::where('vaccine_id', $vaccine->id);
            $stationId === null
                ? $stockQuery->whereNull('health_station_id')
                : $stockQuery->where('health_station_id', $stationId);
            $stock = $stockQuery->lockForUpdate()->first();

            if ($stock) {
                $stock->increment('quantity', $data['quantity']);
            } else {
                VaccineInventory::create([
                    'health_station_id' => $stationId,
                    'vaccine_id' => $vaccine->id,
                    'vaccine' => $vaccine->name,
                    'quantity' => $data['quantity'],
                    'reorder_level' => $data['reorder_level'],
                ]);
            }
        });

        return redirect()->route('inventory.index')->with('success', 'Vaccine stock added or replenished.');
    }

    public function adjustInventory(Request $request, VaccineInventory $stock)
    {
        abort_unless($request->user()->isCho(), 403);
        $data = $request->validate([
            'action' => ['required', Rule::in(['add'])],
            'quantity' => ['required', 'integer', 'min:1', 'max:100000'],
        ]);
        DB::transaction(function () use ($stock, $data) {
            $locked = VaccineInventory::whereKey($stock->id)->lockForUpdate()->firstOrFail();
            $locked->increment('quantity', $data['quantity']);
        });

        return redirect()->route('inventory.index')->with('success', 'Vaccine stock replenished.');
    }

    public function sms(Request $request)
    {
        $query = SmsNotification::with(['child.guardian', 'child.healthStation', 'schedule'])
            ->orderBy('scheduled_date', 'asc')
            ->orderBy('id');
        $this->scopeSmsToUser($query);
        $search = trim((string) $request->query('search', ''));
        $status = strtoupper(trim((string) $request->query('status', '')));
        $status = in_array($status, ['PENDING', 'SENT', 'FAILED'], true) ? $status : '';
        $reminder = (string) $request->query('reminder', '');
        $reminder = in_array($reminder, ['7', '3', '1'], true) ? (int) $reminder : null;
        if ($search !== '') {
            $query->where(function ($notifications) use ($search) {
                $notifications->whereHas('child', fn ($children) => $children->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('baby_book_id', 'like', "%{$search}%"))
                    ->orWhereHas('child.guardian', fn ($guardians) => $guardians->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('contact_number', 'like', "%{$search}%"))
                    ->orWhereHas('schedule', fn ($schedules) => $schedules->where('vaccine', 'like', "%{$search}%"));
            });
        }
        $notifications = $query->get();
        $statusCounts = $notifications->countBy(fn (SmsNotification $notification) => strtolower($notification->status));
        $groupedReminders = $notifications
            ->groupBy(fn (SmsNotification $notification) => $this->smsGroupKey($notification))
            ->map(function ($reminders, $key) {
                $orderedReminders = $reminders->sort(function (SmsNotification $first, SmsNotification $second) {
                    return ((int) $second->reminder_days <=> (int) $first->reminder_days)
                        ?: ($first->id <=> $second->id);
                })->values();
                $firstReminder = $orderedReminders->first();
                $scheduledDate = $reminders
                    ->sortBy(fn (SmsNotification $notification) => $notification->scheduled_date->timestamp)
                    ->first()
                    ->scheduled_date;

                return (object) [
                    'key' => $key,
                    'reminders' => $orderedReminders,
                    'child' => $firstReminder->child,
                    'schedule' => $firstReminder->schedule,
                    'scheduled_date' => $scheduledDate,
                    'next_notification' => $this->nextPendingSmsNotification($orderedReminders),
                    'next_send_notification' => $this->nextSendableSmsNotification($orderedReminders),
                ];
            })
            ->sortBy(fn ($group) => $group->scheduled_date->timestamp)
            ->values();

        $requestedTab = (string) $request->query('tab', 'due_soon');
        $tab = in_array($requestedTab, ['due_soon', 'all', 'sent', 'failed'], true)
            ? $requestedTab
            : 'due_soon';
        $dueSoonLimit = today()->addDays(7);
        $filteredReminders = $groupedReminders->filter(function ($group) use ($tab, $dueSoonLimit, $status, $reminder) {
            $matchesCompatibilityFilters = $group->reminders->contains(fn (SmsNotification $notification) => (
                $status === '' || $notification->status === $status
            ) && (
                $reminder === null || (int) $notification->reminder_days === $reminder
            ));

            if (! $matchesCompatibilityFilters) {
                return false;
            }

            return match ($tab) {
                'all' => true,
                'sent' => $group->reminders->isNotEmpty()
                    && $group->reminders->every(fn (SmsNotification $notification) => $notification->status === 'SENT'),
                'failed' => $group->reminders->contains(fn (SmsNotification $notification) => $notification->status === 'FAILED'),
                default => $group->reminders->contains(fn (SmsNotification $notification) => $notification->status === 'PENDING'
                    && $notification->scheduled_date->lte($dueSoonLimit)),
            };
        })->values();
        $perPage = 15;
        $page = LengthAwarePaginator::resolveCurrentPage();
        $paginatedReminders = new LengthAwarePaginator(
            $filteredReminders->forPage($page, $perPage)->values(),
            $filteredReminders->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return view('operations.sms', [
            'groupedReminders' => $paginatedReminders,
            'search' => $search,
            'tab' => $tab,
            'status' => $status,
            'reminder' => $reminder,
            'summary' => [
                'sent' => $statusCounts->get('sent', 0),
                'pending' => $statusCounts->get('pending', 0),
                'failed' => $statusCounts->get('failed', 0),
            ],
        ]);
    }

    public function updateSmsStatus(Request $request, SmsNotification $notification)
    {
        $this->ensureChildIsInScope($notification->child_id);
        $data = $request->validate(['status' => ['required', Rule::in(['SENT', 'PENDING', 'FAILED'])]]);
        $notification->update([
            'status' => $data['status'],
            'sent_at' => $data['status'] === 'SENT' ? now() : null,
            'failure_reason' => $data['status'] === 'FAILED'
                ? ($notification->failure_reason ?: 'Manually marked failed without sending an SMS.')
                : null,
        ]);
        $message = 'Reminder status updated without sending an SMS.';

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'notification' => $notification->fresh(),
            ]);
        }

        return redirect()->back()->with('success', $message);
    }

    public function sendSms(Request $request, SmsNotification $notification, SkySmsService $skySms)
    {
        $this->ensureChildIsInScope($notification->child_id);
        $notification = $skySms->sendNotification($notification);
        $message = $notification->status === 'SENT'
            ? 'SMS reminder sent.'
            : ($notification->failure_reason ?: 'SMS reminder could not be sent.');

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'notification' => $notification,
            ]);
        }

        if ($notification->status === 'SENT') {
            return redirect()->back()->with('success', $message);
        }

        return redirect()->back()->withErrors(['sms' => $message]);
    }

    public function resendSms(Request $request, SmsNotification $notification, SkySmsService $skySms)
    {
        $this->ensureChildIsInScope($notification->child_id);
        $notification = $skySms->resendNotification($notification);
        $message = $notification->status === 'SENT'
            ? 'SMS reminder resent.'
            : ($notification->failure_reason ?: 'SMS reminder could not be resent.');

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'notification' => $notification,
            ]);
        }

        if ($notification->status === 'SENT') {
            return redirect()->back()->with('success', $message);
        }

        return redirect()->back()->withErrors(['sms' => $message]);
    }

    public function bulkSendSms(Request $request, SkySmsService $skySms)
    {
        $data = $request->validate([
            'notification_ids' => ['required', 'array', 'min:1', 'max:15'],
            'notification_ids.*' => ['required', 'integer', 'distinct', 'exists:sms_notifications,id'],
        ]);
        $query = SmsNotification::query()
            ->with(['child.guardian', 'child.healthStation', 'schedule'])
            ->whereIn('id', $data['notification_ids']);
        $this->scopeSmsToUser($query);
        $notifications = $query->get();
        abort_unless($notifications->count() === count($data['notification_ids']), 403);

        $results = $notifications->map(fn (SmsNotification $notification) => $skySms->sendNotification($notification));
        $sentCount = $results->where('status', 'SENT')->count();
        $failedCount = $results->where('status', 'FAILED')->count();
        $message = "{$sentCount} SMS sent; {$failedCount} failed.";

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'sent' => $sentCount,
                'failed' => $failedCount,
                'notifications' => $results->map(
                    fn (SmsNotification $notification) => $notification->only(['id', 'reminder_days', 'status', 'failure_reason'])
                ),
            ]);
        }

        if ($failedCount > 0) {
            return redirect()->back()->withErrors(['sms' => $message]);
        }

        return redirect()->back()->with('success', $message);
    }

    public function bulkUpdateSmsStatus(Request $request)
    {
        $data = $request->validate([
            'notification_ids' => ['required', 'array', 'min:1', 'max:15'],
            'notification_ids.*' => ['required', 'integer', 'distinct', 'exists:sms_notifications,id'],
        ]);

        $updatedNotifications = DB::transaction(function () use ($data) {
            $selectedQuery = SmsNotification::query()->whereIn('id', $data['notification_ids']);
            $this->scopeSmsToUser($selectedQuery);
            $selectedNotifications = $selectedQuery->lockForUpdate()->get();
            abort_unless($selectedNotifications->count() === count($data['notification_ids']), 403);

            $selectedGroups = $selectedNotifications
                ->unique(fn (SmsNotification $notification) => $this->smsGroupKey($notification))
                ->values();
            $pendingQuery = SmsNotification::query()
                ->where('status', 'PENDING')
                ->where(function ($query) use ($selectedGroups) {
                    foreach ($selectedGroups as $group) {
                        $query->orWhere(function ($groupQuery) use ($group) {
                            if ($group->vaccination_schedule_id !== null) {
                                $groupQuery->where('vaccination_schedule_id', $group->vaccination_schedule_id);
                            } else {
                                $groupQuery->whereNull('vaccination_schedule_id')
                                    ->where('child_id', $group->child_id)
                                    ->whereDate('scheduled_date', $group->scheduled_date->toDateString());
                            }
                        });
                    }
                });
            $this->scopeSmsToUser($pendingQuery);
            $pendingByGroup = $pendingQuery->lockForUpdate()
                ->get()
                ->groupBy(fn (SmsNotification $notification) => $this->smsGroupKey($notification));
            $nextNotifications = $selectedGroups->map(fn (SmsNotification $group) => $this->nextPendingSmsNotification(
                $pendingByGroup->get($this->smsGroupKey($group), collect())
            ))->filter()->values();

            if ($nextNotifications->isEmpty()) {
                throw ValidationException::withMessages([
                    'notification_ids' => 'The selected rows have no pending reminders.',
                ]);
            }

            $sentAt = now();

            return $nextNotifications->map(function (SmsNotification $notification) use ($sentAt) {
                $notification->update(['status' => 'SENT', 'sent_at' => $sentAt]);

                return $notification->fresh();
            })->values();
        });
        $message = $updatedNotifications->count().' pending reminder(s) marked as sent.';

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'notifications' => $updatedNotifications->map(
                    fn (SmsNotification $notification) => $notification->only(['id', 'reminder_days', 'status'])
                ),
            ]);
        }

        return redirect()->back()->with('success', $message);
    }

    private function smsGroupKey(SmsNotification $notification): string
    {
        if ($notification->vaccination_schedule_id !== null) {
            return 'schedule:'.$notification->vaccination_schedule_id;
        }

        return $notification->child_id.'|'.$notification->scheduled_date->toDateString();
    }

    private function nextPendingSmsNotification($notifications): ?SmsNotification
    {
        return $notifications
            ->filter(fn (SmsNotification $notification) => $notification->status === 'PENDING')
            ->sort(function (SmsNotification $first, SmsNotification $second) {
                return ((int) $second->reminder_days <=> (int) $first->reminder_days)
                    ?: ($first->id <=> $second->id);
            })
            ->first();
    }

    private function nextSendableSmsNotification($notifications): ?SmsNotification
    {
        return $notifications
            ->filter(fn (SmsNotification $notification) => in_array($notification->status, ['PENDING', 'FAILED'], true))
            ->sort(function (SmsNotification $first, SmsNotification $second) {
                return ((int) $second->reminder_days <=> (int) $first->reminder_days)
                    ?: ($first->id <=> $second->id);
            })
            ->first();
    }

    private function immunizationRules(?ImmunizationRecord $record = null): array
    {
        $uniqueDose = Rule::unique('immunization_records', 'dose_number')
            ->where(fn ($query) => $query->where('child_id', request('child_id'))->where('vaccine', request('vaccine')));
        if ($record) {
            $uniqueDose->ignore($record->id);
        }

        return [
            'child_id' => ['required', 'exists:children,id'],
            'vaccine' => ['required', Rule::in($this->vaccineNames(false, $record?->vaccine)->all())],
            'dose_number' => ['required', 'integer', 'min:1', 'max:10', $uniqueDose],
            'administered_at' => ['required', 'date', 'before_or_equal:today'],
        ];
    }

    private function childrenForCurrentUser()
    {
        $query = Child::query();
        if (auth()->user()->isBhw()) {
            $query->where('health_station_id', auth()->user()->health_station_id);
        }

        return $query;
    }

    private function ensureChildIsInScope(int $childId): void
    {
        abort_unless($this->childrenForCurrentUser()->whereKey($childId)->exists(), 403);
    }

    private function scopeRecordsToUser($query): void
    {
        if (auth()->user()->isBhw()) {
            $query->whereHas('child', fn ($children) => $children->where('health_station_id', auth()->user()->health_station_id));
        }
    }

    private function scopeSchedulesToUser($query): void
    {
        if (auth()->user()->isBhw()) {
            $query->whereHas('child', fn ($children) => $children->where('health_station_id', auth()->user()->health_station_id));
        }
    }

    private function scopeSmsToUser($query): void
    {
        if (auth()->user()->isBhw()) {
            $query->whereHas('child', fn ($children) => $children->where('health_station_id', auth()->user()->health_station_id));
        }
    }

    private function scopeInventoryToUser($query, $user): void
    {
        if ($user->isBhw()) {
            $query->where('health_station_id', $user->health_station_id ?: -1);
        } elseif (request()->query('station')) {
            $query->where('health_station_id', request()->query('station'));
        }
    }

    private function scheduleSummary(): array
    {
        $query = VaccinationSchedule::query();
        $this->scopeSchedulesToUser($query);
        $statuses = $query->with('vaccineDefinition')->get()->countBy(fn ($schedule) => $schedule->display_status);

        return [
            'pending' => $statuses->get('PENDING', 0),
            'due' => $statuses->get('DUE', 0),
            'overdue' => $statuses->get('OVERDUE', 0),
        ];
    }

    private function vaccineNames(bool $includeInactive = false, ?string $currentName = null)
    {
        $query = Vaccine::query()->orderBy('name');
        if (! $includeInactive) {
            $query->where('is_active', true);
        }
        $names = $query->pluck('name');

        if ($currentName && ! $names->contains($currentName)) {
            $names->push($currentName);
        }

        return $names;
    }
}
