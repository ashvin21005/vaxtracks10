<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\VaccinationScheduleResource;
use App\Models\Child;
use App\Models\SmsNotification;
use App\Models\VaccinationSchedule;
use App\Models\Vaccine;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class VaccinationScheduleController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:150'],
            'status' => ['nullable', Rule::in(['PENDING', 'DUE', 'OVERDUE', 'COMPLETED', 'INVALID'])],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);
        $query = VaccinationSchedule::query()
            ->with(['child', 'vaccineDefinition'])
            ->whereHas('child', fn ($children) => $children->where('health_station_id', $request->user()->health_station_id ?: -1))
            ->when(trim($filters['search'] ?? '') !== '', function ($schedules) use ($filters) {
                $search = trim($filters['search']);
                $schedules->where(function ($matching) use ($search) {
                    $matching->where('vaccine', 'like', "%{$search}%")
                        ->orWhereHas('child', fn ($children) => $children
                            ->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%"));
                });
            })
            ->orderBy('scheduled_date');

        $schedules = $query->get();
        $status = $filters['status'] ?? null;
        if ($status) {
            $schedules = $schedules->filter(fn ($schedule) => $schedule->display_status === $status)->values();
        }
        $perPage = $filters['per_page'] ?? 15;
        $page = LengthAwarePaginator::resolveCurrentPage();
        $paginated = new LengthAwarePaginator(
            $schedules->forPage($page, $perPage)->values(),
            $schedules->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return VaccinationScheduleResource::collection(
            $paginated
        );
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'child_id' => ['required', 'exists:children,id'],
            'vaccine_id' => ['required', 'integer', Rule::exists('vaccines', 'id')->where('is_active', true)],
            'dose_number' => ['required', 'integer', 'min:1', 'max:10'],
            'scheduled_date' => ['required', 'date'],
        ]);
        $child = Child::findOrFail($data['child_id']);
        $this->ensureChildIsInScope($request, $child);
        $vaccine = Vaccine::findOrFail($data['vaccine_id']);
        $existing = VaccinationSchedule::where('child_id', $child->id)
            ->where('vaccine', $vaccine->name)
            ->where('dose_number', $data['dose_number'])
            ->first();
        if ($existing && ! $existing->generated_by_protocol) {
            throw ValidationException::withMessages([
                'dose_number' => 'This child already has a schedule for this vaccine dose. Update the existing schedule date instead.',
            ]);
        }
        $schedule = DB::transaction(function () use ($child, $vaccine, $data) {
            $schedule = VaccinationSchedule::where('child_id', $child->id)
                ->where('vaccine', $vaccine->name)
                ->where('dose_number', $data['dose_number'])
                ->first();
            if ($schedule) {
                $schedule->update(['scheduled_date' => $data['scheduled_date']]);
            } else {
                $schedule = VaccinationSchedule::create([
                    'child_id' => $child->id,
                    'vaccine_id' => $vaccine->id,
                    'vaccine' => $vaccine->name,
                    'dose_number' => $data['dose_number'],
                    'scheduled_date' => $data['scheduled_date'],
                    'due_date' => $data['scheduled_date'],
                    'status' => 'UPCOMING',
                ]);
            }
            $this->queueReminders($schedule);

            return $schedule;
        });

        return (new VaccinationScheduleResource($schedule->load('child')))
            ->response()
            ->setStatusCode(201);
    }

    public function update(Request $request, VaccinationSchedule $schedule)
    {
        $this->ensureScheduleIsInScope($request, $schedule);
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
        DB::transaction(function () use ($schedule, $data) {
            $schedule->update($data);
            if (array_key_exists('scheduled_date', $data)) {
                SmsNotification::where('vaccination_schedule_id', $schedule->id)
                    ->where('status', 'PENDING')
                    ->update(['scheduled_date' => $data['scheduled_date']]);
            }
        });

        return new VaccinationScheduleResource($schedule->fresh()->load('child'));
    }

    public function destroy(Request $request, VaccinationSchedule $schedule)
    {
        $this->ensureScheduleIsInScope($request, $schedule);
        DB::transaction(function () use ($schedule) {
            SmsNotification::where('vaccination_schedule_id', $schedule->id)
                ->where('status', 'PENDING')
                ->delete();
            $schedule->delete();
        });

        return response()->json(['message' => 'Vaccination schedule deleted.']);
    }

    private function queueReminders(VaccinationSchedule $schedule): void
    {
        foreach ([7, 3, 1] as $days) {
            SmsNotification::firstOrCreate([
                'vaccination_schedule_id' => $schedule->id,
                'reminder_days' => $days,
            ], [
                'child_id' => $schedule->child_id,
                'scheduled_date' => $schedule->scheduled_date,
                'status' => 'PENDING',
            ]);
        }
        SmsNotification::where('vaccination_schedule_id', $schedule->id)
            ->where('status', 'PENDING')
            ->update(['scheduled_date' => $schedule->scheduled_date]);
    }

    private function ensureScheduleIsInScope(Request $request, VaccinationSchedule $schedule): void
    {
        $this->ensureChildIsInScope($request, $schedule->child);
    }

    private function ensureChildIsInScope(Request $request, Child $child): void
    {
        abort_unless(
            $request->user()->health_station_id
                && $child->health_station_id === $request->user()->health_station_id,
            403
        );
    }
}
