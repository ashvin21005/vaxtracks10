<?php

namespace Tests\Feature;

use App\Models\Child;
use App\Models\Guardian;
use App\Models\HealthStation;
use App\Models\ImmunizationRecord;
use App\Models\User;
use App\Models\VaccinationSchedule;
use App\Models\Vaccine;
use App\Models\VaccineInventory;
use App\Services\EpiScheduleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PatientWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_bhw_can_choose_any_barangay_from_the_master_list(): void
    {
        DB::table('barangays')->insert([
            [
                'official_name' => 'Barangay 83',
                'name' => 'San Jose',
                'city' => 'Tacloban City',
                'province' => 'Leyte',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'official_name' => 'Barangay 84',
                'name' => 'San Jose',
                'city' => 'Tacloban City',
                'province' => 'Leyte',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'official_name' => 'Barangay 1',
                'name' => 'Libertad',
                'city' => 'Tacloban City',
                'province' => 'Leyte',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
        $station = HealthStation::create(['name' => 'San Jose Station', 'barangay' => 'San Jose']);
        $user = User::factory()->create([
            'role' => 'BHW',
            'health_station_id' => $station->id,
            'must_change_password' => false,
        ]);

        $this->actingAs($user)
            ->get(route('patients.create'))
            ->assertRedirect(route('patients.index', ['register' => 1]));
        $this->get(route('patients.index', ['register' => 1]))
            ->assertOk()
            ->assertSee('Barangay 83 - San Jose')
            ->assertSee('Barangay 84 - San Jose')
            ->assertSee('Barangay 1 - Libertad')
            ->assertSee('patient-registration-dialog')
            ->assertSee('data-modal-success', false)
            ->assertDontSee('Choose from the barangays served by your assigned health station.');

        $this->post(route('patients.store'), [
            'first_name' => 'Mia',
            'last_name' => 'Reyes',
            'sex' => 'Female',
            'date_of_birth' => today()->subMonths(3)->toDateString(),
            'time_of_birth' => '04:20',
            'place_of_birth_type' => 'Hospital',
            'birth_facility' => 'Tacloban City Hospital',
            'birth_weight' => '3.25',
            'purok' => 'Purok 4',
            'street' => 'Rizal Street',
            'barangay' => 'Barangay 1',
            'guardian_first_name' => 'Ana',
            'guardian_middle_name' => 'Maria',
            'guardian_last_name' => 'Reyes',
            'guardian_relationship' => 'Mother',
            'guardian_contact_number' => '09171234567',
            'guardian_alternative_contact_number' => '09981234567',
            'guardian_address_same_as_child' => '1',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('children', [
            'first_name' => 'Mia',
            'barangay' => 'Barangay 1',
            'health_station_id' => $station->id,
        ]);
        $child = Child::where('first_name', 'Mia')->firstOrFail();
        $this->assertSame('Hospital', $child->place_of_birth);
        $this->assertSame('Tacloban City Hospital', $child->birth_facility);
        $this->assertSame('Purok 4, Rizal Street, Barangay 1, Tacloban City', $child->address);
        $this->assertSame('Maria', $child->guardian->middle_name);
        $this->assertSame('09981234567', $child->guardian->alternative_contact_number);
        $this->assertSame($child->address, $child->guardian->address);
        $pentavalent = Vaccine::where('epi_code', 'PENTAVALENT')->firstOrFail();
        $this->assertSame('BB-'.str_pad((string) $child->id, 6, '0', STR_PAD_LEFT), $child->baby_book_id);
        $this->assertSame(15, $child->vaccinationSchedules()->count());
        $schedule = VaccinationSchedule::where('child_id', $child->id)
            ->where('vaccine_id', $pentavalent->id)
            ->where('dose_number', 1)
            ->firstOrFail();
        $this->assertSame($child->date_of_birth->copy()->addDays(42)->toDateString(), $schedule->due_date->toDateString());
        $this->assertSame(today()->toDateString(), $schedule->scheduled_date->toDateString());
        $this->assertTrue($schedule->generated_by_protocol);

        $duplicatePayload = [
            'first_name' => 'Mia',
            'last_name' => 'Reyes',
            'sex' => 'Female',
            'date_of_birth' => today()->subMonths(3)->toDateString(),
            'barangay' => 'Barangay 1',
            'guardian_first_name' => 'Ana',
            'guardian_last_name' => 'Reyes',
            'guardian_relationship' => 'Mother',
            'guardian_contact_number' => '09171234567',
        ];
        $this->from(route('patients.index'))
            ->post(route('patients.store'), $duplicatePayload)
            ->assertSessionHasErrors('duplicate_confirmation');
        $this->from(route('patients.index'))
            ->post(route('patients.store'), $duplicatePayload + ['duplicate_confirmation' => '1'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();
        $this->assertSame(2, Child::where('first_name', 'Mia')->count());
    }

    public function test_bhw_can_add_verified_month_year_history_without_changing_inventory(): void
    {
        [$user, $child] = $this->makeBhwAndChild();
        $child->update(['date_of_birth' => today()->subYears(2)->toDateString()]);
        $vaccine = Vaccine::where('epi_code', 'MMR')->firstOrFail();
        $inventory = VaccineInventory::create([
            'health_station_id' => $child->health_station_id,
            'vaccine_id' => $vaccine->id,
            'vaccine' => $vaccine->name,
            'quantity' => 10,
            'reorder_level' => 2,
        ]);
        $administrationMonth = $child->date_of_birth->copy()->addDays(300);

        $this->actingAs($user)->post(route('patients.historical-immunizations.store', $child), [
            'historical_immunization' => [
                'vaccine_id' => $vaccine->id,
                'dose_number' => 1,
                'date_precision' => 'MONTH',
                'administered_month' => $administrationMonth->month,
                'administered_year' => $administrationMonth->year,
                'verified' => '1',
            ],
        ])->assertRedirect(route('patients.show', $child))->assertSessionHasNoErrors();

        $record = ImmunizationRecord::where('child_id', $child->id)->firstOrFail();
        $this->assertNull($record->administered_at);
        $this->assertSame('MONTH', $record->date_precision);
        $this->assertSame('HISTORICAL', $record->record_type);
        $this->assertSame($user->id, $record->verified_by);
        $this->assertNotNull($record->verified_at);
        $this->assertDatabaseHas('vaccine_inventory', ['id' => $inventory->id, 'quantity' => 10]);
        $this->assertDatabaseHas('vaccination_schedules', [
            'child_id' => $child->id,
            'vaccine_id' => $vaccine->id,
            'dose_number' => 1,
            'status' => 'COMPLETED',
        ]);
        $nextSchedule = VaccinationSchedule::where('child_id', $child->id)
            ->where('vaccine_id', $vaccine->id)
            ->where('dose_number', 2)
            ->firstOrFail();
        $this->assertSame(
            $administrationMonth->copy()->endOfMonth()->addDays(84)->toDateString(),
            $nextSchedule->due_date->toDateString()
        );
    }

    public function test_year_only_history_keeps_year_precision_and_displays_only_the_year(): void
    {
        [$user, $child] = $this->makeBhwAndChild();
        $child->update(['date_of_birth' => '2023-01-15']);
        $vaccine = Vaccine::where('epi_code', 'MMR')->firstOrFail();

        $this->actingAs($user)->post(route('patients.historical-immunizations.store', $child), [
            'historical_immunization' => [
                'vaccine_id' => $vaccine->id,
                'dose_number' => 1,
                'history_status' => 'ADMINISTERED',
                'date_precision' => 'YEAR',
                'administered_year' => 2024,
                'verification_source' => 'HOSPITAL_RECORD',
                'verified' => '1',
            ],
        ])->assertRedirect(route('patients.show', $child))->assertSessionHasNoErrors();

        $record = ImmunizationRecord::where('child_id', $child->id)->firstOrFail();
        $this->assertSame('YEAR', $record->date_precision);
        $this->assertSame(2024, (int) $record->administered_year);
        $this->assertNull($record->administered_at);
        $this->assertSame('2024', $record->administration_date_label);
    }

    public function test_unknown_history_holds_the_vaccine_series_for_review(): void
    {
        [$user, $child] = $this->makeBhwAndChild();
        $child->update(['date_of_birth' => today()->subDays(180)->toDateString()]);
        $vaccine = Vaccine::where('epi_code', 'PENTAVALENT')->firstOrFail();

        $this->actingAs($user)->post(route('patients.historical-immunizations.store', $child), [
            'historical_immunization' => [
                'vaccine_id' => $vaccine->id,
                'dose_number' => 1,
                'history_status' => 'UNKNOWN',
            ],
        ])->assertRedirect(route('patients.show', $child))->assertSessionHasNoErrors();

        $record = ImmunizationRecord::where('child_id', $child->id)->where('vaccine_id', $vaccine->id)->firstOrFail();
        $this->assertNull($record->administered_at);
        $this->assertNull($record->verified_by);
        $this->assertSame('REVIEW', $record->status);

        $schedules = VaccinationSchedule::where('child_id', $child->id)->where('vaccine_id', $vaccine->id)->get();
        $this->assertCount(3, $schedules);
        $this->assertTrue($schedules->every(fn ($schedule) => $schedule->status === 'REVIEW' && $schedule->review_required));
        $this->assertSame('FOR REVIEW', $schedules->first()->display_status);
        $this->assertSame(0, \App\Models\SmsNotification::whereIn('vaccination_schedule_id', $schedules->pluck('id'))->count());

        $this->post(route('patients.historical-immunizations.store', $child), [
            'historical_immunization' => [
                'vaccine_id' => $vaccine->id,
                'dose_number' => 1,
                'history_status' => 'ADMINISTERED',
                'date_precision' => 'DAY',
                'administered_at' => today()->subDays(100)->toDateString(),
                'verification_source' => 'VACCINATION_CARD',
                'verified' => '1',
            ],
        ])->assertRedirect(route('patients.show', $child))->assertSessionHasNoErrors();

        $this->assertSame(1, ImmunizationRecord::where('child_id', $child->id)->where('vaccine_id', $vaccine->id)->count());
        $this->assertSame('COMPLETED', $record->fresh()->status);
        $this->assertSame('VACCINATION_CARD', $record->fresh()->verification_source);
        $this->assertSame('COMPLETED', VaccinationSchedule::where('child_id', $child->id)
            ->where('vaccine_id', $vaccine->id)->where('dose_number', 1)->value('status'));
    }

    public function test_not_administered_history_generates_spaced_catch_up_doses(): void
    {
        [$user, $child] = $this->makeBhwAndChild();
        $child->update(['date_of_birth' => today()->subDays(100)->toDateString()]);
        $vaccine = Vaccine::where('epi_code', 'PENTAVALENT')->firstOrFail();

        $this->actingAs($user)->post(route('patients.historical-immunizations.store', $child), [
            'historical_immunization' => [
                'vaccine_id' => $vaccine->id,
                'dose_number' => 1,
                'history_status' => 'NOT_ADMINISTERED',
            ],
        ])->assertRedirect(route('patients.show', $child))->assertSessionHasNoErrors();

        $record = ImmunizationRecord::where('child_id', $child->id)->where('vaccine_id', $vaccine->id)->firstOrFail();
        $this->assertSame('NOT_ADMINISTERED', $record->status);
        $this->assertNull($record->verified_by);

        $schedules = VaccinationSchedule::where('child_id', $child->id)
            ->where('vaccine_id', $vaccine->id)
            ->orderBy('dose_number')
            ->get();
        $this->assertSame('UPCOMING', $schedules[0]->status);
        $this->assertSame(today()->toDateString(), $schedules[0]->scheduled_date->toDateString());
        $this->assertSame(today()->addDays(28)->toDateString(), $schedules[1]->scheduled_date->toDateString());
        $this->assertSame(today()->addDays(56)->toDateString(), $schedules[2]->scheduled_date->toDateString());
    }

    public function test_schedule_uses_the_later_of_minimum_age_and_actual_dose_interval(): void
    {
        [, $child] = $this->makeBhwAndChild();
        $child->update(['date_of_birth' => today()->subDays(120)->toDateString()]);
        $vaccine = Vaccine::where('epi_code', 'PENTAVALENT')->firstOrFail();
        $previousDoseDate = today()->subDays(20);
        $previousDose = ImmunizationRecord::create([
            'child_id' => $child->id,
            'vaccine_id' => $vaccine->id,
            'vaccine' => $vaccine->name,
            'dose_number' => 1,
            'administered_at' => $previousDoseDate,
            'date_precision' => 'DAY',
            'record_type' => 'LIVE',
            'status' => 'COMPLETED',
        ]);
        $service = app(EpiScheduleService::class);

        $validation = $service->validationForLiveDose($child, $vaccine, 2, today()->toDateString());
        $this->assertSame('INVALID', $validation['status']);

        $service->syncChild($child->fresh());
        $secondDose = VaccinationSchedule::where('child_id', $child->id)
            ->where('vaccine_id', $vaccine->id)
            ->where('dose_number', 2)
            ->firstOrFail();
        $this->assertSame(
            $previousDoseDate->copy()->addDays(28)->toDateString(),
            $secondDose->due_date->toDateString()
        );
        $this->assertDatabaseHas('immunization_records', ['id' => $previousDose->id]);
    }

    public function test_catch_up_schedule_spaces_unadministered_doses_instead_of_setting_them_all_to_today(): void
    {
        [, $child] = $this->makeBhwAndChild();
        $child->update(['date_of_birth' => today()->subDays(100)->toDateString()]);
        $vaccine = Vaccine::where('epi_code', 'PENTAVALENT')->firstOrFail();

        app(EpiScheduleService::class)->syncChild($child->fresh());

        $schedules = VaccinationSchedule::where('child_id', $child->id)
            ->where('vaccine_id', $vaccine->id)
            ->orderBy('dose_number')
            ->get();

        $this->assertSame(3, $schedules->count());
        $this->assertSame(today()->toDateString(), $schedules[0]->scheduled_date->toDateString());
        $this->assertSame(today()->addDays(28)->toDateString(), $schedules[1]->scheduled_date->toDateString());
        $this->assertSame(today()->addDays(56)->toDateString(), $schedules[2]->scheduled_date->toDateString());
    }

    public function test_bhw_can_register_search_view_and_edit_station_patient(): void
    {
        DB::table('barangays')->insert([
            'official_name' => 'West',
            'name' => 'West',
            'city' => 'Tacloban City',
            'province' => 'Leyte',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $station = HealthStation::create(['name' => 'West Station', 'barangay' => 'West']);
        $user = User::factory()->create(['role' => 'BHW', 'health_station_id' => $station->id, 'must_change_password' => false]);
        $this->actingAs($user);

        $this->post(route('patients.store'), [
            'first_name' => 'Mia',
            'last_name' => 'Reyes',
            'sex' => 'Female',
            'date_of_birth' => today()->subMonths(3)->toDateString(),
            'barangay' => 'West',
            'guardian_first_name' => 'Ana',
            'guardian_last_name' => 'Reyes',
            'guardian_relationship' => 'Mother',
            'guardian_contact_number' => '09171234567',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $child = Child::with('guardian')->firstOrFail();
        $this->assertSame($station->id, $child->health_station_id);
        $this->assertSame('West', $child->barangay);
        $this->get(route('patients.index', ['search' => 'Mia', 'status' => 'ACTIVE']))->assertOk()->assertSee('Mia Reyes');
        $this->get(route('patients.show', $child))->assertOk()->assertSee('Ana Reyes')->assertSee('West Station');
        $this->put(route('patients.update', $child), [
            'first_name' => 'Mia',
            'last_name' => 'Reyes',
            'sex' => 'Female',
            'date_of_birth' => today()->subMonths(3)->toDateString(),
            'barangay' => 'West',
            'status' => 'INACTIVE',
            'guardian_first_name' => 'Ana',
            'guardian_last_name' => 'Reyes',
            'guardian_relationship' => 'Mother',
            'guardian_contact_number' => '09171234567',
        ])->assertRedirect();
        $this->assertDatabaseHas('children', ['id' => $child->id, 'status' => 'INACTIVE', 'barangay' => 'West']);
    }

    public function test_bhw_cannot_view_another_stations_patient(): void
    {
        $station = HealthStation::create(['name' => 'Station One', 'barangay' => 'One']);
        $otherStation = HealthStation::create(['name' => 'Station Two', 'barangay' => 'Two']);
        $user = User::factory()->create(['role' => 'BHW', 'health_station_id' => $station->id, 'must_change_password' => false]);
        $guardian = Guardian::create(['first_name' => 'Nina', 'last_name' => 'Cruz', 'relationship' => 'Mother', 'contact_number' => '09171234567']);
        $child = Child::create([
            'guardian_id' => $guardian->id,
            'health_station_id' => $otherStation->id,
            'first_name' => 'Leo',
            'last_name' => 'Cruz',
            'sex' => 'Male',
            'date_of_birth' => today()->subMonths(3)->toDateString(),
            'barangay' => 'Two',
            'status' => 'ACTIVE',
        ]);

        $this->actingAs($user)->get(route('patients.show', $child))->assertForbidden();
    }

    public function test_bhw_can_archive_a_patient_from_the_list_and_history_is_retained(): void
    {
        [$user, $child] = $this->makeBhwAndChild();
        $record = \App\Models\ImmunizationRecord::create([
            'child_id' => $child->id,
            'vaccine' => 'BCG',
            'dose_number' => 1,
            'administered_at' => today(),
        ]);

        $this->actingAs($user)
            ->get(route('patients.index'))
            ->assertOk()
            ->assertSee('Archive');

        $this->delete(route('patients.destroy', $child))
            ->assertRedirect(route('patients.index'))
            ->assertSessionHas('success');

        $this->assertSoftDeleted('children', ['id' => $child->id]);
        $this->assertDatabaseHas('immunization_records', ['id' => $record->id]);
    }

    private function makeBhwAndChild(): array
    {
        $station = HealthStation::create(['name' => 'Patient Test Station', 'barangay' => 'Patient Test']);
        $user = User::factory()->create([
            'role' => 'BHW',
            'health_station_id' => $station->id,
            'must_change_password' => false,
        ]);
        $guardian = Guardian::create([
            'first_name' => 'Ana',
            'last_name' => 'Reyes',
            'relationship' => 'Mother',
            'contact_number' => '09171234567',
        ]);
        $child = Child::create([
            'guardian_id' => $guardian->id,
            'health_station_id' => $station->id,
            'first_name' => 'Mia',
            'last_name' => 'Reyes',
            'sex' => 'Female',
            'date_of_birth' => today()->subMonths(3),
            'barangay' => 'Patient Test',
            'status' => 'ACTIVE',
        ]);

        return [$user, $child];
    }
}
