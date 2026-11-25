<?php

namespace App\Services;

use App\Models\Child;
use App\Models\ImmunizationRecord;
use App\Models\SmsNotification;
use App\Models\User;
use App\Models\VaccinationSchedule;
use App\Models\Vaccine;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class EpiScheduleService
{
    public function protocols(): array
    {
        return config('immunization.epi', []);
    }

    public function historicalVaccines()
    {
        return Vaccine::whereNotNull('epi_code')->where('is_active', true)->orderBy('name')->get();
    }

    public function historicalDoseRules(string $prefix = ''): array
    {
        return [
            $prefix.'vaccine_id' => [
                'required',
                'integer',
                Rule::exists('vaccines', 'id')->where(fn ($query) => $query->whereNotNull('epi_code')->where('is_active', true)),
            ],
            $prefix.'dose_number' => ['required', 'integer', 'min:1', 'max:3'],
            $prefix.'history_status' => ['sometimes', Rule::in(['ADMINISTERED', 'NOT_ADMINISTERED', 'UNKNOWN'])],
            $prefix.'date_precision' => ['exclude_if:'.$prefix.'history_status,NOT_ADMINISTERED', 'exclude_if:'.$prefix.'history_status,UNKNOWN', 'required_if:'.$prefix.'history_status,ADMINISTERED', 'nullable', Rule::in(['DAY', 'MONTH', 'YEAR'])],
            $prefix.'administered_at' => ['exclude_if:'.$prefix.'history_status,NOT_ADMINISTERED', 'exclude_if:'.$prefix.'history_status,UNKNOWN', 'required_if:'.$prefix.'date_precision,DAY', 'nullable', 'date', 'before_or_equal:today'],
            $prefix.'administered_month' => ['exclude_if:'.$prefix.'history_status,NOT_ADMINISTERED', 'exclude_if:'.$prefix.'history_status,UNKNOWN', 'required_if:'.$prefix.'date_precision,MONTH', 'nullable', 'integer', 'min:1', 'max:12'],
            $prefix.'administered_year' => ['exclude_if:'.$prefix.'history_status,NOT_ADMINISTERED', 'exclude_if:'.$prefix.'history_status,UNKNOWN', 'required_if:'.$prefix.'date_precision,MONTH,YEAR', 'nullable', 'integer', 'min:1900', 'max:'.today()->year],
            $prefix.'administering_facility_type' => ['nullable', Rule::in(['HOSPITAL', 'RHU', 'BARANGAY_HEALTH_CENTER', 'PRIVATE_CLINIC', 'OTHER'])],
            $prefix.'administering_facility_name' => ['nullable', 'string', 'max:150'],
            $prefix.'verification_source' => ['nullable', Rule::in(['BABY_BOOK', 'VACCINATION_CARD', 'HOSPITAL_RECORD', 'HEALTH_CENTER_RECORD', 'OTHER_DOCUMENT'])],
            $prefix.'verified' => ['sometimes', 'accepted'],
            $prefix.'guardian_reported' => ['sometimes', 'boolean'],
            $prefix.'vaccine_brand' => ['nullable', 'string', 'max:100'],
            $prefix.'manufacturer' => ['nullable', 'string', 'max:100'],
            $prefix.'batch_number' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function historicalImmunizationsRules(): array
    {
        return [
            'historical_immunizations' => ['sometimes', 'array', 'max:15'],
            'historical_immunizations.*' => ['required', 'array'],
            ...$this->historicalDoseRules('historical_immunizations.*.'),
        ];
    }

    public function createHistoricalDose(Child $child, array $data, User $user): ImmunizationRecord
    {
        $vaccine = Vaccine::whereKey($data['vaccine_id'])
            ->whereNotNull('epi_code')
            ->where('is_active', true)
            ->firstOrFail();
        $protocol = $this->protocolFor($vaccine);
        $doseNumber = (int) $data['dose_number'];
        $historyStatus = $data['history_status'] ?? 'ADMINISTERED';

        if (! $protocol || ! isset($protocol['doses'][$doseNumber - 1])) {
            throw ValidationException::withMessages([
                'dose_number' => 'The selected vaccine does not have this dose in the EPI schedule.',
            ]);
        }

        $existingRecord = ImmunizationRecord::query()
            ->where('child_id', $child->id)
            ->where('vaccine_id', $vaccine->id)
            ->where('dose_number', $doseNumber)
            ->first();
        $canResolvePlaceholder = $existingRecord
            && in_array($existingRecord->history_status, ['UNKNOWN', 'NOT_ADMINISTERED'], true)
            && $historyStatus === 'ADMINISTERED';

        if ($existingRecord && ! $canResolvePlaceholder) {
            throw ValidationException::withMessages([
                'dose_number' => 'This vaccine dose is already recorded for this child.',
            ]);
        }

        $verified = filter_var($data['verified'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $recordData = [
            'vaccine_id' => $vaccine->id,
            'vaccine' => $vaccine->name,
            'dose_number' => $doseNumber,
            'history_status' => $historyStatus,
            'administered_at' => $historyStatus === 'ADMINISTERED' && ($data['date_precision'] ?? null) === 'DAY' ? $data['administered_at'] : null,
            'administered_month' => $historyStatus === 'ADMINISTERED' && ($data['date_precision'] ?? null) === 'MONTH' ? $data['administered_month'] : null,
            'administered_year' => $historyStatus === 'ADMINISTERED' && in_array($data['date_precision'] ?? null, ['MONTH', 'YEAR'], true) ? $data['administered_year'] : null,
            'date_precision' => $historyStatus === 'ADMINISTERED' ? ($data['date_precision'] ?? null) : null,
            'record_type' => 'HISTORICAL',
            'administering_facility_type' => $data['administering_facility_type'] ?? null,
            'administering_facility_name' => $data['administering_facility_name'] ?? null,
            'verification_source' => $verified ? ($data['verification_source'] ?? 'BABY_BOOK') : null,
            'guardian_reported' => ! $verified && filter_var($data['guardian_reported'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'vaccine_brand' => $data['vaccine_brand'] ?? null,
            'manufacturer' => $data['manufacturer'] ?? null,
            'batch_number' => $data['batch_number'] ?? null,
            'validation_status' => 'UNVERIFIABLE',
            'verified_by' => $verified ? $user->id : null,
            'verified_at' => $verified ? now() : null,
            'recorded_by' => $user->id,
            'status' => $historyStatus === 'NOT_ADMINISTERED' ? 'NOT_ADMINISTERED' : 'REVIEW',
        ];
        if ($existingRecord) {
            $existingRecord->fill($recordData)->save();
            $record = $existingRecord;
        } else {
            $record = $child->immunizationRecords()->create($recordData);
        }

        if ($historyStatus !== 'ADMINISTERED' || ! $verified) {
            return $record->fresh();
        }

        $previousRecord = $doseNumber > 1
            ? $child->immunizationRecords()->where('vaccine_id', $vaccine->id)->where('dose_number', $doseNumber - 1)->first()
            : null;
        $validation = $this->validateAdministration($child, $vaccine, $doseNumber, $record, $previousRecord);
        $record->update([
            'validation_status' => $validation['status'],
            'status' => $validation['status'] === 'VALID' ? 'COMPLETED' : 'REVIEW',
        ]);

        return $record->fresh();
    }

    public function validationForLiveDose(
        Child $child,
        Vaccine $vaccine,
        int $doseNumber,
        string $administeredAt,
        ?ImmunizationRecord $ignore = null
    ): array {
        $record = new ImmunizationRecord([
            'date_precision' => 'DAY',
            'administered_at' => $administeredAt,
        ]);
        $previous = $doseNumber > 1
            ? $child->immunizationRecords()
                ->where('vaccine_id', $vaccine->id)
                ->where('dose_number', $doseNumber - 1)
                ->where('history_status', 'ADMINISTERED')
                ->where('validation_status', 'VALID')
                ->when($ignore, fn ($query) => $query->where('id', '!=', $ignore->id))
                ->first()
            : null;

        return $this->validateAdministration($child, $vaccine, $doseNumber, $record, $previous);
    }

    public function syncChild(Child $child): void
    {
        DB::transaction(function () use ($child) {
            $child->load('immunizationRecords');

            foreach ($this->protocols() as $code => $protocol) {
                $vaccine = Vaccine::where('epi_code', $code)->first();
                if (! $vaccine) {
                    continue;
                }

                $previousRecord = null;
                $previousTarget = null;
                $previousInvalid = false;
                $previousUnverifiable = false;

                foreach ($protocol['doses'] as $index => $minimumAgeDays) {
                    $doseNumber = $index + 1;
                    $record = $child->immunizationRecords
                        ->first(fn ($item) => $item->vaccine_id === $vaccine->id && (int) $item->dose_number === $doseNumber);
                    $ageDue = $child->date_of_birth->copy()->addDays($minimumAgeDays);
                    $interval = (int) ($protocol['interval_days'][$index - 1] ?? 0);

                    if ($previousUnverifiable) {
                        $this->markDoseAndFollowingForReview($child, $vaccine, $protocol, $index);
                        break;
                    }

                    if ($record && $record->history_status === 'UNKNOWN') {
                        $this->markDoseAndFollowingForReview($child, $vaccine, $protocol, $index);
                        break;
                    }

                    if ($record && $record->history_status === 'NOT_ADMINISTERED') {
                        $record = null;
                    }

                    if ($record && $record->record_type === 'HISTORICAL' && ! $record->verified_at) {
                        $this->markDoseAndFollowingForReview($child, $vaccine, $protocol, $index);
                        break;
                    }

                    if ($record) {
                        $validation = $this->validateAdministration(
                            $child,
                            $vaccine,
                            $doseNumber,
                            $record,
                            $previousRecord
                        );
                        $record->update([
                            'validation_status' => $validation['status'],
                            'status' => $validation['status'] === 'INVALID' ? 'INVALID' : 'COMPLETED',
                        ]);
                        [, $rangeEnd] = $this->administrationRange($record);
                        $dueDate = $ageDue;
                        if ($previousRecord && $interval > 0) {
                            [, $previousEnd] = $this->administrationRange($previousRecord);
                            $dueDate = $this->laterDate($dueDate, $previousEnd->copy()->addDays($interval));
                        }
                        $schedule = $this->scheduleFor($child, $vaccine, $doseNumber);
                        $schedule->fill([
                            'child_id' => $child->id,
                            'vaccine' => $vaccine->name,
                            'scheduled_date' => $rangeEnd->toDateString(),
                            'due_date' => $dueDate->toDateString(),
                            'generated_by_protocol' => true,
                            'review_required' => $validation['status'] !== 'VALID',
                            'status' => $validation['status'] === 'VALID' ? 'COMPLETED' : 'REVIEW',
                        ])->save();
                        $this->removePendingReminders($schedule);
                        $previousInvalid = $validation['status'] === 'INVALID';
                        $previousUnverifiable = $validation['status'] !== 'VALID';
                        $previousRecord = $record;
                        $previousTarget = $rangeEnd;

                        continue;
                    }

                    $dueDate = $ageDue;
                    if ($interval > 0 && $previousRecord) {
                        [, $previousEnd] = $this->administrationRange($previousRecord);
                        $dueDate = $this->laterDate($dueDate, $previousEnd->copy()->addDays($interval));
                    } elseif ($interval > 0 && $previousTarget) {
                        $dueDate = $this->laterDate($dueDate, $previousTarget->copy()->addDays($interval));
                    }

                    $targetDate = $dueDate->lt(today()) ? today() : $dueDate->copy();
                    $schedule = $this->scheduleFor($child, $vaccine, $doseNumber);
                    $schedule->fill([
                        'child_id' => $child->id,
                        'vaccine' => $vaccine->name,
                        'scheduled_date' => $targetDate->toDateString(),
                        'due_date' => $dueDate->toDateString(),
                        'generated_by_protocol' => true,
                        'review_required' => $previousInvalid || $previousUnverifiable,
                        'status' => 'UPCOMING',
                    ])->save();
                    $this->syncReminders($schedule);
                    $previousTarget = $targetDate;
                    $previousRecord = null;
                }
            }
        });
    }

    public function validateAdministration(
        Child $child,
        Vaccine $vaccine,
        int $doseNumber,
        ImmunizationRecord $record,
        ?ImmunizationRecord $previousRecord
    ): array {
        $protocol = $this->protocolFor($vaccine);
        if (! $protocol) {
            return ['status' => 'VALID', 'note' => null];
        }
        if (! isset($protocol['doses'][$doseNumber - 1])) {
            return ['status' => 'INVALID', 'note' => 'Dose is not in the configured EPI protocol.'];
        }

        [$start, $end] = $this->administrationRange($record);
        $minimumAgeDate = $child->date_of_birth->copy()->addDays($protocol['doses'][$doseNumber - 1]);

        if ($end->lt($minimumAgeDate)) {
            return ['status' => 'INVALID', 'note' => 'Administration was before the minimum age.'];
        }

        if ($start->lt($minimumAgeDate)) {
            return ['status' => 'UNVERIFIABLE', 'note' => 'Month/year precision overlaps the minimum-age date.'];
        }

        if ($doseNumber === 1) {
            return ['status' => 'VALID', 'note' => null];
        }

        if (! $previousRecord) {
            return ['status' => 'UNVERIFIABLE', 'note' => 'The preceding dose is not recorded.'];
        }

        $minimumInterval = (int) ($protocol['interval_days'][$doseNumber - 2] ?? 0);
        if ($minimumInterval === 0) {
            return ['status' => 'VALID', 'note' => null];
        }

        [$previousStart, $previousEnd] = $this->administrationRange($previousRecord);
        $shortestPossibleInterval = $previousEnd->diffInDays($start, false);
        $longestPossibleInterval = $previousStart->diffInDays($end, false);

        if ($longestPossibleInterval < $minimumInterval) {
            return ['status' => 'INVALID', 'note' => 'The minimum interval between doses was not met.'];
        }

        if ($shortestPossibleInterval < $minimumInterval) {
            return ['status' => 'UNVERIFIABLE', 'note' => 'Month/year precision cannot verify the minimum interval.'];
        }

        return ['status' => 'VALID', 'note' => null];
    }

    private function protocolFor(Vaccine $vaccine): ?array
    {
        return $this->protocols()[$vaccine->epi_code] ?? null;
    }

    private function scheduleFor(Child $child, Vaccine $vaccine, int $doseNumber): VaccinationSchedule
    {
        return VaccinationSchedule::where('child_id', $child->id)
            ->where('vaccine_id', $vaccine->id)
            ->where('dose_number', $doseNumber)
            ->first()
            ?? VaccinationSchedule::where('child_id', $child->id)
                ->where('vaccine', $vaccine->name)
                ->where('dose_number', $doseNumber)
                ->first()
            ?? new VaccinationSchedule([
                'child_id' => $child->id,
                'vaccine_id' => $vaccine->id,
                'vaccine' => $vaccine->name,
                'dose_number' => $doseNumber,
            ]);
    }

    private function administrationRange(ImmunizationRecord $record): array
    {
        if ($record->date_precision === 'YEAR') {
            $start = Carbon::create((int) $record->administered_year, 1, 1)->startOfDay();

            return [$start, $start->copy()->endOfYear()->startOfDay()];
        }

        if ($record->date_precision === 'MONTH') {
            $start = Carbon::create((int) $record->administered_year, (int) $record->administered_month, 1)->startOfDay();

            return [$start, $start->copy()->endOfMonth()->startOfDay()];
        }

        $date = Carbon::parse($record->administered_at)->startOfDay();

        return [$date, $date->copy()];
    }

    private function laterDate(Carbon $left, Carbon $right): Carbon
    {
        return $left->gte($right) ? $left : $right;
    }

    private function syncReminders(VaccinationSchedule $schedule): void
    {
        if ($schedule->scheduled_date->lt(today())) {
            return;
        }

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

    private function removePendingReminders(VaccinationSchedule $schedule): void
    {
        SmsNotification::where('vaccination_schedule_id', $schedule->id)
            ->where('status', 'PENDING')
            ->delete();
    }

    private function markDoseAndFollowingForReview(Child $child, Vaccine $vaccine, array $protocol, int $startIndex): void
    {
        foreach (array_slice($protocol['doses'], $startIndex, null, true) as $index => $minimumAgeDays) {
            $dueDate = $child->date_of_birth->copy()->addDays($minimumAgeDays);
            $scheduledDate = $dueDate->lt(today()) ? today() : $dueDate;
            $schedule = $this->scheduleFor($child, $vaccine, $index + 1);
            $schedule->fill([
                'child_id' => $child->id,
                'vaccine' => $vaccine->name,
                'scheduled_date' => $scheduledDate->toDateString(),
                'due_date' => $dueDate->toDateString(),
                'generated_by_protocol' => true,
                'review_required' => true,
                'status' => 'REVIEW',
            ])->save();
            $this->removePendingReminders($schedule);
        }
    }
}
