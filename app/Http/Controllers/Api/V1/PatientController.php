<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ChildResource;
use App\Models\Child;
use App\Models\Guardian;
use App\Services\EpiScheduleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PatientController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:150'],
            'barangay' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['ACTIVE', 'INACTIVE'])],
            'include_archived' => ['sometimes', 'boolean'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $query = ! empty($filters['include_archived']) ? Child::withTrashed() : Child::query();
        $this->scopeToUser($query, $request);
        $search = trim($filters['search'] ?? '');

        if ($search !== '') {
            $query->where(function ($children) use ($search) {
                $children->where('first_name', 'like', "%{$search}%")
                    ->orWhere('middle_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('barangay', 'like', "%{$search}%")
                    ->orWhereHas('guardian', fn ($guardians) => $guardians
                        ->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('contact_number', 'like', "%{$search}%"));
            });
        }

        $query->when($filters['barangay'] ?? null, fn ($children, $barangay) => $children->where('barangay', $barangay))
            ->when($filters['status'] ?? null, fn ($children, $status) => $children->where('status', $status));

        return ChildResource::collection(
            $query->with(['guardian', 'healthStation'])
                ->latest()
                ->paginate($filters['per_page'] ?? 15)
                ->withQueryString()
        );
    }

    public function store(Request $request)
    {
        $data = $request->validate($this->createRules());
        app(\App\Services\PatientRegistrationService::class)->ensureNoUnconfirmedDuplicate($data);
        $user = $request->user();
        abort_unless($user->health_station_id, 403, 'A health station must be assigned before registering patients.');

        $historicalDoses = $data['historical_immunizations'] ?? [];
        unset($data['historical_immunizations']);

        $child = DB::transaction(function () use ($data, $user, $historicalDoses, $request) {
            $childData = collect($data)->only([
                'first_name', 'middle_name', 'last_name', 'sex', 'date_of_birth',
                'time_of_birth', 'place_of_birth', 'place_of_birth_type', 'place_of_birth_details',
                'birth_facility', 'birth_weight', 'purok', 'street', 'barangay', 'address',
            ])->all();
            $childData['place_of_birth'] = $childData['place_of_birth_type'] ?? null;
            $childAddressParts = array_filter([$childData['purok'] ?? null, $childData['street'] ?? null, $childData['barangay'] ?? null]);
            $childData['address'] = $childAddressParts ? implode(', ', $childAddressParts).', Tacloban City' : null;
            if (! empty($data['guardian']['same_as_child_address'])) {
                $data['guardian']['address'] = $childData['address'];
            }
            unset($data['guardian']['same_as_child_address']);
            $guardian = $this->resolveGuardian($data, $user->health_station_id);
            $childData['health_station_id'] = $user->health_station_id;

            $child = $guardian->children()->create($childData);
            $scheduleService = app(EpiScheduleService::class);
            foreach ($historicalDoses as $dose) {
                $scheduleService->createHistoricalDose($child, $dose, $request->user());
            }
            $scheduleService->syncChild($child);

            return $child;
        });

        return (new ChildResource($child->load([
            'guardian',
            'healthStation',
            'vaccinationSchedules.vaccineDefinition',
            'immunizationRecords.vaccineDefinition',
            'immunizationRecords.verifier',
        ])))
            ->response()
            ->setStatusCode(201);
    }

    public function storeHistoricalImmunization(Request $request, Child $child)
    {
        $this->ensureChildIsInScope($child, $request);
        $validated = $request->validate(
            app(EpiScheduleService::class)->historicalDoseRules('historical_immunization.')
        );

        DB::transaction(function () use ($child, $validated, $request) {
            $scheduleService = app(EpiScheduleService::class);
            $scheduleService->createHistoricalDose($child, $validated['historical_immunization'], $request->user());
            $scheduleService->syncChild($child);
        });

        return (new ChildResource($child->fresh()->load([
            'guardian',
            'healthStation',
            'vaccinationSchedules.vaccineDefinition',
            'immunizationRecords.vaccineDefinition',
            'immunizationRecords.verifier',
        ])))->response();
    }

    public function show(Request $request, Child $child)
    {
        $this->ensureChildIsInScope($child, $request);
        $child->load([
            'guardian',
            'healthStation',
            'immunizationRecords.vaccineDefinition',
            'immunizationRecords.recorder',
            'immunizationRecords.verifier',
            'vaccinationSchedules.vaccineDefinition',
        ]);

        return new ChildResource($child);
    }

    public function update(Request $request, Child $child)
    {
        $this->ensureChildIsInScope($child, $request);
        $data = $request->validate($this->updateRules($child));

        $dobChanged = isset($data['date_of_birth'])
            && $child->date_of_birth->toDateString() !== $data['date_of_birth'];

        DB::transaction(function () use ($data, $child, $request) {
            $childData = collect($data)->only([
                'first_name', 'middle_name', 'last_name', 'sex', 'date_of_birth',
                'place_of_birth', 'birth_weight', 'barangay', 'address', 'status',
                'time_of_birth', 'place_of_birth_type', 'place_of_birth_details', 'birth_facility',
                'purok', 'street',
            ])->all();
            if (array_key_exists('place_of_birth_type', $childData)) {
                $childData['place_of_birth'] = $childData['place_of_birth_type'];
            }
            if (array_key_exists('purok', $childData) || array_key_exists('street', $childData)) {
                $childAddressParts = array_filter([$childData['purok'] ?? null, $childData['street'] ?? null, $childData['barangay'] ?? null]);
                $childData['address'] = $childAddressParts ? implode(', ', $childAddressParts).', Tacloban City' : null;
            }
            $child->update($childData);

            if (isset($data['guardian_id'])) {
                $this->ensureGuardianIsInScope(Guardian::findOrFail($data['guardian_id']), $request);
                $child->update(['guardian_id' => $data['guardian_id']]);
            } elseif (isset($data['guardian'])) {
                if (! empty($data['guardian']['same_as_child_address'])) {
                    $data['guardian']['address'] = $childData['address'] ?? $child->address;
                }
                unset($data['guardian']['same_as_child_address']);
                $guardian = $child->guardian;
                $otherStationChildren = $guardian->children()
                    ->where('health_station_id', '!=', $request->user()->health_station_id)
                    ->exists();

                if ($otherStationChildren) {
                    $replacement = $guardian->replicate();
                    $replacement->fill($data['guardian']);
                    $replacement->save();
                    $child->update(['guardian_id' => $replacement->id]);
                } else {
                    $guardian->update($data['guardian']);
                }
            }
        });

        if ($dobChanged) {
            app(EpiScheduleService::class)->syncChild($child->fresh());
        }

        return new ChildResource($child->fresh()->load([
            'guardian',
            'healthStation',
            'vaccinationSchedules.vaccineDefinition',
        ]));
    }

    public function destroy(Request $request, Child $child)
    {
        $this->ensureChildIsInScope($child, $request);
        $child->delete();

        return response()->json(['message' => 'Patient archived; related history has been retained.']);
    }

    private function createRules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'sex' => ['required', Rule::in(['Male', 'Female'])],
            'date_of_birth' => ['required', 'date', 'before_or_equal:today'],
            'time_of_birth' => ['nullable', 'date_format:H:i'],
            'place_of_birth' => ['nullable', 'string', 'max:150'],
            'place_of_birth_type' => ['nullable', Rule::in(['Hospital', 'Birthing Facility', 'Other'])],
            'place_of_birth_details' => ['nullable', 'string', 'max:150', 'required_if:place_of_birth_type,Other'],
            'birth_facility' => ['nullable', 'string', 'max:150'],
            'birth_weight' => ['nullable', 'numeric', 'min:0.5', 'max:9.99'],
            'purok' => ['nullable', 'string', 'max:100'],
            'street' => ['nullable', 'string', 'max:150'],
            'barangay' => ['required', Rule::exists('barangays', 'official_name')],
            'duplicate_confirmation' => ['sometimes', 'boolean'],
            'address' => ['nullable', 'string', 'max:255'],
            'guardian_id' => ['required_without:guardian', 'nullable', 'integer', 'exists:guardians,id'],
            'guardian' => ['required_without:guardian_id', 'array'],
            ...$this->guardianRules('guardian.', false, 'required_with:guardian'),
            ...app(EpiScheduleService::class)->historicalImmunizationsRules(),
        ];
    }

    private function updateRules(Child $child): array
    {
        return [
            'first_name' => ['sometimes', 'required', 'string', 'max:100'],
            'middle_name' => ['sometimes', 'nullable', 'string', 'max:100'],
            'last_name' => ['sometimes', 'required', 'string', 'max:100'],
            'sex' => ['sometimes', 'required', Rule::in(['Male', 'Female'])],
            'date_of_birth' => ['sometimes', 'required', 'date', 'before_or_equal:today'],
            'time_of_birth' => ['sometimes', 'nullable', 'date_format:H:i'],
            'place_of_birth' => ['sometimes', 'nullable', 'string', 'max:150'],
            'place_of_birth_type' => ['sometimes', 'nullable', Rule::in(['Hospital', 'Birthing Facility', 'Other'])],
            'place_of_birth_details' => ['sometimes', 'nullable', 'string', 'max:150', 'required_if:place_of_birth_type,Other'],
            'birth_facility' => ['sometimes', 'nullable', 'string', 'max:150'],
            'birth_weight' => ['sometimes', 'nullable', 'numeric', 'min:0.5', 'max:9.99'],
            'purok' => ['sometimes', 'nullable', 'string', 'max:100'],
            'street' => ['sometimes', 'nullable', 'string', 'max:150'],
            'barangay' => [
                'sometimes',
                'required',
                Rule::in(DB::table('barangays')->pluck('official_name')->push($child->barangay)->unique()->all()),
            ],
            'address' => ['sometimes', 'nullable', 'string', 'max:255'],
            'status' => ['sometimes', Rule::in(['ACTIVE', 'INACTIVE'])],
            'guardian_id' => ['sometimes', 'required', 'integer', 'exists:guardians,id', 'prohibited_with:guardian'],
            'guardian' => ['sometimes', 'array', 'prohibited_with:guardian_id'],
            ...$this->guardianRules('guardian.', true),
        ];
    }

    private function guardianRules(string $prefix, bool $partial = false, ?string $presenceOverride = null): array
    {
        $presence = $presenceOverride ?? ($partial ? 'sometimes|required' : 'required');

        return [
            $prefix.'first_name' => [$presence, 'string', 'max:100'],
            $prefix.'last_name' => [$presence, 'string', 'max:100'],
            $prefix.'relationship' => [$presence, Rule::in(['Mother', 'Father', 'Grandparent', 'Legal Guardian', 'Other'])],
            $prefix.'contact_number' => [$presence, 'regex:/^(09|\+639)\d{9}$/'],
            $prefix.'middle_name' => ['sometimes', 'nullable', 'string', 'max:100'],
            $prefix.'alternative_contact_number' => ['sometimes', 'nullable', 'regex:/^(09|\+639)\d{9}$/'],
            $prefix.'occupation' => ['sometimes', 'nullable', 'string', 'max:100'],
            $prefix.'address' => ['sometimes', 'nullable', 'string', 'max:255'],
            $prefix.'same_as_child_address' => ['sometimes', 'boolean'],
        ];
    }

    private function resolveGuardian(array $data, int $stationId): Guardian
    {
        if (isset($data['guardian_id'])) {
            $guardian = Guardian::findOrFail($data['guardian_id']);
            $this->ensureGuardianIsAvailableAtStation($guardian, $stationId);

            return $guardian;
        }

        return Guardian::create($data['guardian']);
    }

    private function ensureGuardianIsInScope(Guardian $guardian, Request $request): void
    {
        abort_unless(
            $guardian->children()->where('health_station_id', $request->user()->health_station_id)->exists(),
            404
        );
    }

    private function ensureGuardianIsAvailableAtStation(Guardian $guardian, int $stationId): void
    {
        abort_if(
            $guardian->children()->where('health_station_id', '!=', $stationId)->exists()
                && ! $guardian->children()->where('health_station_id', $stationId)->exists(),
            403
        );
    }

    private function ensureChildIsInScope(Child $child, Request $request): void
    {
        abort_unless($child->health_station_id === $request->user()->health_station_id, 403);
    }

    private function scopeToUser($query, Request $request): void
    {
        $query->where('health_station_id', $request->user()->health_station_id ?: -1);
    }
}
