<?php

namespace App\Http\Controllers;

use App\Models\Child;
use App\Models\Guardian;
use App\Models\HealthStation;
use App\Services\EpiScheduleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PatientController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('search', ''));
        $status = $request->query('status');
        $barangay = $request->query('barangay');

        $query = Child::with(['guardian', 'healthStation']);
        if ($request->user()->isBhw()) {
            $request->user()->health_station_id
                ? $query->where('health_station_id', $request->user()->health_station_id)
                : $query->whereRaw('1 = 0');
        }

        $query
            ->when($search, function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('first_name', 'like', "%{$search}%")
                        ->orWhere('middle_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('barangay', 'like', "%{$search}%")
                        ->orWhereHas('guardian', function ($g) use ($search) {
                            $g->where('first_name', 'like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%")
                                ->orWhere('contact_number', 'like', "%{$search}%");
                        });
                });
            })
            ->when(in_array($status, ['ACTIVE', 'INACTIVE'], true), fn ($query) => $query->where('status', $status))
            ->when($barangay, fn ($query) => $query->where('barangay', $barangay));

        $barangays = (clone $query)->reorder()->distinct()->orderBy('barangay')->pluck('barangay');
        $children = $query->latest()->paginate(10)->withQueryString();

        return view('patients.index', [
            'children' => $children,
            'search' => $search,
            'status' => $status,
            'barangay' => $barangay,
            'barangays' => $barangays,
            'registrationForm' => $this->registrationFormData(),
        ]);
    }

    public function create(Request $request)
    {
        return redirect()->route('patients.index', ['register' => 1]);
    }

    private function registrationFormData(): array
    {
        return [
            'child' => new Child,
            'guardian' => new Guardian,
            'stations' => HealthStation::orderBy('name')->get(),
            'barangays' => $this->availableBarangays(),
            'historicalVaccines' => app(EpiScheduleService::class)->historicalVaccines(),
        ];
    }

    public function store(Request $request)
    {
        $validated = $request->validate($this->rules(), $this->messages());
        app(\App\Services\PatientRegistrationService::class)->ensureNoUnconfirmedDuplicate($validated);
        $historicalDoses = $validated['historical_immunizations'] ?? [];
        unset($validated['historical_immunizations']);
        [$childData, $guardianData] = $this->splitData(
            $validated
        );
        [$childData, $guardianData] = $this->normalizeRegistrationData($childData, $guardianData);

        if ($request->user()->isBhw()) {
            abort_unless($request->user()->health_station_id, 403);
            $childData['health_station_id'] = $request->user()->health_station_id;
        }

        $child = DB::transaction(function () use ($childData, $guardianData, $historicalDoses, $request) {
            $guardian = Guardian::create($guardianData);
            $child = $guardian->children()->create($childData);

            $scheduleService = app(EpiScheduleService::class);
            foreach ($historicalDoses as $dose) {
                $scheduleService->createHistoricalDose($child, $dose, $request->user());
            }
            $scheduleService->syncChild($child);

            return $child;
        });

        return redirect()->route('patients.show', $child)
            ->with('success', 'Child registered successfully.');
    }

    public function show(Child $child)
    {
        $this->ensureChildIsInScope($child);
        $child->load([
            'guardian',
            'healthStation',
            'immunizationRecords.recorder',
            'immunizationRecords.verifier',
            'immunizationRecords.vaccineDefinition',
            'vaccinationSchedules.vaccineDefinition',
        ]);

        return view('patients.show', [
            'child' => $child,
            'historicalVaccines' => app(EpiScheduleService::class)->historicalVaccines(),
        ]);
    }

    public function edit(Child $child)
    {
        $this->ensureChildIsInScope($child);

        return view('patients.edit', [
            'child' => $child,
            'guardian' => $child->guardian,
            'stations' => HealthStation::orderBy('name')->get(),
            'barangays' => $this->availableBarangays(),
        ]);
    }

    public function update(Request $request, Child $child)
    {
        $this->ensureChildIsInScope($child);
        [$childData, $guardianData] = $this->splitData(
            $request->validate($this->rules(), $this->messages())
        );
        [$childData, $guardianData] = $this->normalizeRegistrationData($childData, $guardianData);

        if ($request->user()->isBhw()) {
            $childData['health_station_id'] = $request->user()->health_station_id;
        }

        DB::transaction(function () use ($child, $childData, $guardianData) {
            $child->update($childData);
            $child->guardian->update($guardianData);
        });
        app(EpiScheduleService::class)->syncChild($child->fresh());

        return redirect()->route('patients.show', $child)
            ->with('success', 'Profile updated successfully.');
    }

    public function storeHistoricalImmunization(Request $request, Child $child)
    {
        $this->ensureChildIsInScope($child);
        $validated = $request->validate(
            app(EpiScheduleService::class)->historicalDoseRules('historical_immunization.')
        );
        $dose = $validated['historical_immunization'];

        DB::transaction(function () use ($child, $dose, $request) {
            $scheduleService = app(EpiScheduleService::class);
            $scheduleService->createHistoricalDose($child, $dose, $request->user());
            $scheduleService->syncChild($child);
        });

        return redirect()->route('patients.show', $child)
            ->with('success', 'Verified historical immunization recorded.');
    }

    public function destroy(Child $child)
    {
        $this->ensureChildIsInScope($child);
        $child->delete();

        return redirect()->route('patients.index')->with('success', 'Patient archived. Immunization and schedule history has been retained.');
    }

    /** Splits validated input into child fields and guardian_* fields. */
    private function splitData(array $data): array
    {
        $child = [];
        $guardian = [];

        foreach ($data as $key => $value) {
            if (str_starts_with($key, 'guardian_')) {
                $guardian[substr($key, 9)] = $value;
            } else {
                $child[$key] = $value;
            }
        }

        return [$child, $guardian];
    }

    private function normalizeRegistrationData(array $child, array $guardian): array
    {
        $child['place_of_birth'] = $child['place_of_birth_type'] ?? null;
        $childAddressParts = array_filter([$child['purok'] ?? null, $child['street'] ?? null, $child['barangay'] ?? null]);
        $child['address'] = $childAddressParts ? implode(', ', $childAddressParts).', Tacloban City' : null;

        $sameAddress = (bool) ($guardian['address_same_as_child'] ?? false);
        unset($guardian['address_same_as_child']);
        if ($sameAddress) {
            $guardian['address'] = $child['address'] ?: null;
        }

        return [$child, $guardian];
    }

    private function rules(): array
    {
        $validBarangays = $this->availableBarangays()->pluck('official_name')->all();

        if (request()->routeIs('patients.update')) {
            $child = request()->route('child');
            if ($child instanceof Child && ! in_array($child->barangay, $validBarangays, true)) {
                $validBarangays[] = $child->barangay;
            }
        }

        $rules = [
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'sex' => ['required', 'in:Male,Female'],
            'date_of_birth' => ['required', 'date', 'before_or_equal:today'],
            'time_of_birth' => ['nullable', 'date_format:H:i'],
            'place_of_birth' => ['nullable', 'string', 'max:150'],
            'place_of_birth_type' => ['nullable', Rule::in(['Hospital', 'Birthing Facility', 'Other'])],
            'place_of_birth_details' => ['nullable', 'string', 'max:150', 'required_if:place_of_birth_type,Other'],
            'birth_facility' => ['nullable', 'string', 'max:150'],
            'birth_weight' => ['nullable', 'numeric', 'min:0.5', 'max:9.99'],
            'purok' => ['nullable', 'string', 'max:100'],
            'street' => ['nullable', 'string', 'max:150'],
            'barangay' => ['required', 'string', 'max:100', Rule::in($validBarangays)],
            'duplicate_confirmation' => ['sometimes', 'boolean'],
            'address' => ['nullable', 'string', 'max:255'],
            'health_station_id' => ['nullable', 'exists:health_stations,id'],

            'guardian_first_name' => ['required', 'string', 'max:100'],
            'guardian_last_name' => ['required', 'string', 'max:100'],
            'guardian_relationship' => ['required', 'in:Mother,Father,Grandparent,Legal Guardian,Other'],
            'guardian_contact_number' => ['required', 'regex:/^(09|\+639)\d{9}$/'],
            'guardian_alternative_contact_number' => ['nullable', 'regex:/^(09|\+639)\d{9}$/'],
            'guardian_middle_name' => ['nullable', 'string', 'max:100'],
            'guardian_occupation' => ['nullable', 'string', 'max:100'],
            'guardian_address' => ['nullable', 'string', 'max:255'],
            'guardian_address_same_as_child' => ['sometimes', 'boolean'],
        ];

        if (request()->routeIs('patients.store')) {
            $rules += app(EpiScheduleService::class)->historicalImmunizationsRules();
        }

        if (request()->routeIs('patients.update')) {
            $rules['status'] = ['required', 'in:ACTIVE,INACTIVE'];
        }

        return $rules;
    }

    private function messages(): array
    {
        return [
            'guardian_contact_number.regex' => 'Enter a valid PH mobile number (e.g. 09171234567).',
            'date_of_birth.before_or_equal' => 'Date of birth cannot be in the future.',
            'guardian_first_name.required' => 'Guardian first name is required.',
            'guardian_last_name.required' => 'Guardian last name is required.',
            'barangay.in' => 'Please select a barangay from the list.',
        ];
    }

    private function availableBarangays()
    {
        return DB::table('barangays')
            ->orderBy('official_name')
            ->get(['official_name', 'name']);
    }

    private function ensureChildIsInScope(Child $child): void
    {
        $user = request()->user();
        abort_if($user->isBhw() && $child->health_station_id !== $user->health_station_id, 403);
    }
}
