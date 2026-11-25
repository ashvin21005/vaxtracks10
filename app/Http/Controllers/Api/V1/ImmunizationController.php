<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ImmunizationRecordResource;
use App\Models\Child;
use App\Models\ImmunizationRecord;
use App\Models\VaccinationSchedule;
use App\Models\Vaccine;
use App\Services\EpiScheduleService;
use App\Services\VaccineInventoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ImmunizationController extends Controller
{
    public function index(Request $request, Child $child)
    {
        $this->ensureChildIsInScope($child, $request);
        $records = $child->immunizationRecords()
            ->with(['vaccineDefinition', 'recorder'])
            ->orderByDesc('administered_at')
            ->paginate(50);

        return ImmunizationRecordResource::collection($records);
    }

    public function store(Request $request, Child $child)
    {
        $this->ensureChildIsInScope($child, $request);
        $data = $request->validate($this->rules($child));
        $vaccine = Vaccine::whereKey($data['vaccine_id'])->where('is_active', true)->firstOrFail();

        $record = DB::transaction(function () use ($child, $data, $request, $vaccine) {
            abort_unless($request->user()->health_station_id && $child->health_station_id, 403);
            $validation = app(EpiScheduleService::class)->validationForLiveDose(
                $child,
                $vaccine,
                (int) $data['dose_number'],
                $data['administered_at']
            );
            app(VaccineInventoryService::class)->deductDose($child->health_station_id, $vaccine);

            $record = $child->immunizationRecords()->create([
                'vaccine_id' => $vaccine->id,
                'vaccine' => $vaccine->name,
                'dose_number' => $data['dose_number'],
                'administered_at' => $data['administered_at'],
                'recorded_by' => $request->user()->id,
                'validation_status' => $validation['status'],
                'status' => $validation['status'] === 'INVALID' ? 'INVALID' : 'COMPLETED',
            ]);

            return $record;
        });
        app(EpiScheduleService::class)->syncChild($child->fresh());

        return (new ImmunizationRecordResource($record->load(['vaccineDefinition', 'recorder', 'verifier'])))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, ImmunizationRecord $record)
    {
        $child = $record->child()->withTrashed()->firstOrFail();
        $this->ensureChildIsInScope($child, $request);

        return new ImmunizationRecordResource($record->load(['vaccineDefinition', 'recorder']));
    }

    public function update(Request $request, ImmunizationRecord $record)
    {
        $child = $record->child()->firstOrFail();
        $sourceChildId = (int) $child->id;
        $this->ensureChildIsInScope($child, $request);
        $data = $request->validate($this->rules($child, $record));
        $targetChild = isset($data['child_id']) ? Child::findOrFail($data['child_id']) : $child;
        $this->ensureChildIsInScope($targetChild, $request);
        $vaccine = Vaccine::whereKey($data['vaccine_id'])->firstOrFail();
        $validation = app(EpiScheduleService::class)->validationForLiveDose(
            $targetChild,
            $vaccine,
            (int) $data['dose_number'],
            $data['administered_at'],
            $record
        );

        DB::transaction(function () use ($record, $child, $targetChild, $data, $vaccine, $validation) {
            $oldVaccine = $record->vaccine;
            $oldDoseNumber = (int) $record->dose_number;
            if (
                $child->id !== $targetChild->id
                || $oldVaccine !== $vaccine->name
                || $oldDoseNumber !== (int) $data['dose_number']
            ) {
                VaccinationSchedule::where('child_id', $child->id)
                    ->where('vaccine', $oldVaccine)
                    ->where('dose_number', $oldDoseNumber)
                    ->update(['status' => 'UPCOMING']);
            }
            $record->update([
                'child_id' => $targetChild->id,
                'vaccine_id' => $vaccine->id,
                'vaccine' => $vaccine->name,
                'dose_number' => $data['dose_number'],
                'administered_at' => $data['administered_at'],
                'date_precision' => 'DAY',
                'record_type' => 'LIVE',
                'validation_status' => $validation['status'],
                'status' => $validation['status'] === 'INVALID' ? 'INVALID' : 'COMPLETED',
            ]);
        });
        app(EpiScheduleService::class)->syncChild($targetChild->fresh());
        if ($sourceChildId !== $targetChild->id) {
            app(EpiScheduleService::class)->syncChild(Child::findOrFail($sourceChildId));
        }

        return new ImmunizationRecordResource($record->fresh()->load(['vaccineDefinition', 'recorder', 'verifier']));
    }

    private function rules(Child $child, ?ImmunizationRecord $record = null): array
    {
        $childId = request()->input('child_id', $child->id);
        $vaccineExists = Rule::exists('vaccines', 'id')
            ->where(fn ($query) => $query
                ->where('is_active', true)
                ->orWhere('id', $record?->vaccine_id ?? -1));
        $uniqueDose = Rule::unique('immunization_records', 'dose_number')
            ->where(fn ($query) => $query
                ->where('child_id', $childId)
                ->where('vaccine_id', request('vaccine_id')));
        if ($record) {
            $uniqueDose->ignore($record->id);
        }

        return [
            'child_id' => ['sometimes', 'required', 'integer', 'exists:children,id'],
            'vaccine_id' => ['required', 'integer', $vaccineExists],
            'dose_number' => ['required', 'integer', 'min:1', 'max:10', $uniqueDose],
            'administered_at' => ['required', 'date', 'before_or_equal:today'],
        ];
    }

    private function ensureChildIsInScope(Child $child, Request $request): void
    {
        abort_unless(
            $request->user()->health_station_id
                && $child->health_station_id === $request->user()->health_station_id,
            403
        );
    }
}
