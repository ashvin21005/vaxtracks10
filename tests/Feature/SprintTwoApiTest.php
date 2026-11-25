<?php

namespace Tests\Feature;

use App\Models\Child;
use App\Models\HealthStation;
use App\Models\HistoricalIndicator;
use App\Models\ImmunizationRecord;
use App\Models\User;
use App\Models\Vaccine;
use App\Models\VaccineInventory;
use App\Models\VaccinationSchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SprintTwoApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_tokens_authenticate_users_and_enforce_roles(): void
    {
        $bhw = $this->makeBhw('Token Station');

        $this->getJson('/api/v1/me')->assertUnauthorized();

        $response = $this->postJson('/api/v1/tokens', [
            'login' => $bhw->username,
            'password' => 'password',
            'device_name' => 'test-client',
        ])->assertCreated()->assertJsonPath('user.role', 'BHW');

        $token = $response->json('access_token');
        $this->withToken($token)->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.id', $bhw->id);
        $this->withToken($token)->deleteJson('/api/v1/tokens/current')->assertOk();
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/v1/me')->assertUnauthorized();
    }

    public function test_bhw_patient_guardian_and_immunization_endpoints_are_station_scoped_and_archive_safely(): void
    {
        $this->seedBarangays();
        [$bhw, $token] = $this->makeBhwAndToken('North Station');
        $vaccine = Vaccine::where('name', 'BCG')->firstOrFail();

        $created = $this->withToken($token)->postJson('/api/v1/patients', $this->patientPayload())->assertCreated();
        $childId = $created->json('data.id');
        $this->assertDatabaseHas('children', [
            'id' => $childId,
            'health_station_id' => $bhw->health_station_id,
            'barangay' => 'Barangay 1',
        ]);
        $this->assertSame('Reyes', $created->json('data.guardian.last_name'));
        $stock = VaccineInventory::create([
            'health_station_id' => $bhw->health_station_id,
            'vaccine_id' => $vaccine->id,
            'vaccine' => $vaccine->name,
            'quantity' => 2,
            'reorder_level' => 1,
        ]);

        $this->withToken($token)->getJson('/api/v1/patients?search=Mia')->assertOk()
            ->assertJsonPath('data.0.id', $childId);
        $this->withToken($token)->getJson('/api/v1/guardians?search=Ana')->assertOk()
            ->assertJsonPath('data.0.contact_number', '09171234567');

        $recordResponse = $this->withToken($token)->postJson("/api/v1/patients/{$childId}/immunizations", [
            'vaccine_id' => $vaccine->id,
            'dose_number' => 1,
            'administered_at' => today()->toDateString(),
        ])->assertCreated()->assertJsonPath('data.vaccine.name', 'BCG');
        $recordId = $recordResponse->json('data.id');
        $this->assertDatabaseHas('immunization_records', [
            'id' => $recordId,
            'vaccine_id' => $vaccine->id,
            'recorded_by' => $bhw->id,
        ]);
        $this->assertDatabaseHas('vaccine_inventory', ['id' => $stock->id, 'quantity' => 1]);

        $this->withToken($token)->postJson("/api/v1/patients/{$childId}/immunizations", [
            'vaccine_id' => $vaccine->id,
            'dose_number' => 1,
            'administered_at' => today()->toDateString(),
        ])->assertUnprocessable();
        $this->withToken($token)->postJson("/api/v1/patients/{$childId}/immunizations", [
            'vaccine_id' => $vaccine->id,
            'dose_number' => 2,
            'administered_at' => today()->addDay()->toDateString(),
        ])->assertUnprocessable();
        $stock->update(['quantity' => 0]);
        $this->withToken($token)->postJson("/api/v1/patients/{$childId}/immunizations", [
            'vaccine_id' => $vaccine->id,
            'dose_number' => 2,
            'administered_at' => today()->toDateString(),
        ])->assertUnprocessable()->assertJsonValidationErrors('inventory');
        $this->assertDatabaseMissing('immunization_records', ['child_id' => $childId, 'dose_number' => 2]);

        $secondBhw = $this->makeBhw('North Station', 'second.bhw');
        $secondToken = $secondBhw->createToken('second-client')->plainTextToken;
        $this->app['auth']->forgetGuards();
        $this->withToken($secondToken)->putJson("/api/v1/immunizations/{$recordId}", [
            'vaccine_id' => $vaccine->id,
            'dose_number' => 1,
            'administered_at' => today()->toDateString(),
        ])->assertOk();
        $this->assertDatabaseHas('immunization_records', ['id' => $recordId, 'recorded_by' => $bhw->id]);
        $this->assertDatabaseHas('vaccine_inventory', ['id' => $stock->id, 'quantity' => 0]);

        $otherBhw = $this->makeBhw('South Station', 'other.bhw');
        $otherToken = $otherBhw->createToken('other-client')->plainTextToken;
        $this->app['auth']->forgetGuards();
        $this->withToken($otherToken)->getJson("/api/v1/patients/{$childId}")->assertForbidden();
        $this->app['auth']->forgetGuards();
        $this->withToken($otherToken)->getJson("/api/v1/immunizations/{$recordId}")->assertForbidden();

        $this->app['auth']->forgetGuards();
        $this->withToken($token)->deleteJson("/api/v1/patients/{$childId}")
            ->assertOk()
            ->assertJsonPath('message', 'Patient archived; related history has been retained.');
        $this->assertSoftDeleted('children', ['id' => $childId]);
        $this->assertDatabaseHas('immunization_records', ['id' => $recordId, 'child_id' => $childId]);
        $this->withToken($token)->getJson('/api/v1/patients')->assertOk()->assertJsonCount(0, 'data');
        $archivedResponse = $this->withToken($token)->getJson('/api/v1/patients?include_archived=1')->assertOk();
        $this->assertNotNull($archivedResponse->json('data.0.archived_at'));
        $this->withToken($token)->getJson("/api/v1/immunizations/{$recordId}")->assertOk();
    }

    public function test_same_origin_session_authenticates_browser_requests_to_the_api(): void
    {
        $this->seedBarangays();
        $bhw = $this->makeBhw('Session API Station');
        $origin = rtrim(config('app.url'), '/');

        $this->actingAs($bhw)->withHeaders([
            'Origin' => $origin,
            'Referer' => $origin.'/patients/create',
        ])->postJson('/api/v1/patients', $this->patientPayload())
            ->assertCreated()
            ->assertJsonPath('data.first_name', 'Mia');
    }

    public function test_api_patient_registration_accepts_verified_month_year_history_and_returns_epi_schedule(): void
    {
        $this->seedBarangays();
        [$bhw, $token] = $this->makeBhwAndToken('Historical API Station');
        $vaccine = Vaccine::where('epi_code', 'MMR')->firstOrFail();
        $dateOfBirth = today()->subYears(2);
        $administrationMonth = $dateOfBirth->copy()->addDays(300);
        $payload = $this->patientPayload([
            'date_of_birth' => $dateOfBirth->toDateString(),
            'historical_immunizations' => [[
                'vaccine_id' => $vaccine->id,
                'dose_number' => 1,
                'date_precision' => 'MONTH',
                'administered_month' => $administrationMonth->month,
                'administered_year' => $administrationMonth->year,
                'verified' => '1',
            ]],
        ]);

        $response = $this->withToken($token)->postJson('/api/v1/patients', $payload)
            ->assertCreated()
            ->assertJsonPath('data.baby_book_id', 'BB-000001')
            ->assertJsonPath('data.immunization_records.0.date_precision', 'MONTH')
            ->assertJsonPath('data.immunization_records.0.record_type', 'HISTORICAL');
        $childId = $response->json('data.id');
        $record = ImmunizationRecord::where('child_id', $childId)->firstOrFail();

        $this->assertNull($record->administered_at);
        $this->assertSame($bhw->id, $record->verified_by);
        $this->assertSame(15, count($response->json('data.vaccination_schedules')));
    }

    public function test_api_registration_saves_optional_details_and_holds_unknown_history_for_review(): void
    {
        $this->seedBarangays();
        [$bhw, $token] = $this->makeBhwAndToken('Registration Detail Station');
        $vaccine = Vaccine::where('epi_code', 'PENTAVALENT')->firstOrFail();
        $payload = $this->patientPayload([
            'date_of_birth' => today()->subDays(100)->toDateString(),
            'time_of_birth' => '04:20',
            'place_of_birth_type' => 'Other',
            'place_of_birth_details' => 'Relative home',
            'birth_facility' => 'Tacloban City Hospital',
            'birth_weight' => 3.25,
            'purok' => 'Purok 4',
            'street' => 'Rizal Street',
            'guardian' => [
                'middle_name' => 'Maria',
                'alternative_contact_number' => '09981234567',
                'same_as_child_address' => true,
            ],
            'historical_immunizations' => [[
                'vaccine_id' => $vaccine->id,
                'dose_number' => 1,
                'history_status' => 'UNKNOWN',
            ]],
        ]);

        $response = $this->withToken($token)->postJson('/api/v1/patients', $payload)
            ->assertCreated()
            ->assertJsonPath('data.place_of_birth', 'Other')
            ->assertJsonPath('data.birth_facility', 'Tacloban City Hospital')
            ->assertJsonPath('data.guardian.middle_name', 'Maria')
            ->assertJsonPath('data.immunization_records.0.history_status', 'UNKNOWN');

        $childId = $response->json('data.id');
        $this->assertStringStartsWith('04:20', $response->json('data.time_of_birth'));
        $child = Child::with('guardian')->findOrFail($childId);
        $this->assertSame('Purok 4, Rizal Street, Barangay 1, Tacloban City', $child->address);
        $this->assertSame($child->address, $child->guardian->address);
        $this->assertNull($child->immunizationRecords()->firstOrFail()->verified_by);
        $this->assertTrue(VaccinationSchedule::where('child_id', $childId)
            ->where('vaccine_id', $vaccine->id)
            ->get()
            ->every(fn ($schedule) => $schedule->status === 'REVIEW' && $schedule->review_required));
        $this->assertSame($bhw->id, $child->health_station_id ? $bhw->id : null);

        $duplicatePayload = $this->patientPayload(['date_of_birth' => today()->subDays(100)->toDateString()]);
        $this->withToken($token)->postJson('/api/v1/patients', $duplicatePayload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('duplicate_confirmation');
        $duplicatePayload['duplicate_confirmation'] = true;
        $this->withToken($token)->postJson('/api/v1/patients', $duplicatePayload)->assertCreated();
    }

    public function test_bhw_can_confirm_a_scheduled_vaccination_and_deduct_one_inventory_dose(): void
    {
        $this->seedBarangays();
        [$bhw, $token] = $this->makeBhwAndToken('Confirm Vaccine Station');
        $vaccine = Vaccine::where('name', 'BCG')->firstOrFail();
        $childResponse = $this->withToken($token)->postJson('/api/v1/patients', $this->patientPayload())
            ->assertCreated();
        $childId = $childResponse->json('data.id');
        $scheduleId = VaccinationSchedule::where('child_id', $childId)
            ->where('vaccine_id', $vaccine->id)
            ->where('dose_number', 1)
            ->value('id');
        $stock = VaccineInventory::create([
            'health_station_id' => $bhw->health_station_id,
            'vaccine_id' => $vaccine->id,
            'vaccine' => $vaccine->name,
            'quantity' => 3,
            'reorder_level' => 1,
        ]);

        $this->actingAs($bhw)->get(route('schedule.index'))
            ->assertOk()
            ->assertSee('Confirm vaccinated')
            ->assertSee('type="button" data-confirmation-trigger', false)
            ->assertSee('data-api-resource="scheduled-immunization"', false)
            ->assertSee('Confirm that Mia Reyes received BCG dose 1 today?', false);

        $this->app['auth']->forgetGuards();
        $this->withToken($token)->postJson("/api/v1/patients/{$childId}/immunizations", [
            'vaccine_id' => $vaccine->id,
            'dose_number' => 1,
            'administered_at' => today()->toDateString(),
        ])->assertCreated()
            ->assertJsonPath('data.status', 'COMPLETED');

        $this->assertDatabaseHas('vaccine_inventory', ['id' => $stock->id, 'quantity' => 2]);
        $this->assertDatabaseHas('vaccination_schedules', ['id' => $scheduleId, 'status' => 'COMPLETED']);
        $this->assertDatabaseHas('immunization_records', [
            'child_id' => $childId,
            'vaccine_id' => $vaccine->id,
            'dose_number' => 1,
            'recorded_by' => $bhw->id,
        ]);
    }

    public function test_bhw_inventory_and_schedule_apis_are_station_scoped_and_keep_reminders_synced(): void
    {
        $this->seedBarangays();
        [$bhw, $token] = $this->makeBhwAndToken('Inventory API Station');
        $vaccine = Vaccine::where('name', 'BCG')->firstOrFail();
        $childResponse = $this->withToken($token)->postJson('/api/v1/patients', $this->patientPayload())->assertCreated();
        $childId = $childResponse->json('data.id');

        $stock = VaccineInventory::create([
            'health_station_id' => $bhw->health_station_id,
            'vaccine_id' => $vaccine->id,
            'vaccine' => $vaccine->name,
            'quantity' => 8,
            'reorder_level' => 2,
        ]);
        $this->withToken($token)->getJson('/api/v1/inventory')->assertOk()
            ->assertJsonPath('data.0.quantity', 8);
        $this->withToken($token)->postJson('/api/v1/inventory', [
            'vaccine_id' => $vaccine->id,
            'quantity' => 8,
            'reorder_level' => 2,
            'health_station_id' => $bhw->health_station_id,
        ])->assertForbidden();
        $this->withToken($token)->postJson("/api/v1/inventory/{$stock->id}/adjust", [
            'action' => 'add',
            'quantity' => 2,
        ])->assertForbidden();
        $this->assertDatabaseHas('vaccine_inventory', ['id' => $stock->id, 'quantity' => 8]);

        $scheduleResponse = $this->withToken($token)->postJson('/api/v1/vaccination-schedules', [
            'child_id' => $childId,
            'vaccine_id' => $vaccine->id,
            'dose_number' => 1,
            'scheduled_date' => today()->addDays(7)->toDateString(),
        ])->assertCreated()->assertJsonPath('data.status', 'OVERDUE');
        $scheduleId = $scheduleResponse->json('data.id');
        $this->withToken($token)->getJson('/api/v1/vaccination-schedules?status=OVERDUE')->assertOk()
            ->assertJsonFragment(['id' => $scheduleId]);

        $newDate = today()->addDays(12)->toDateString();
        $this->withToken($token)->patchJson("/api/v1/vaccination-schedules/{$scheduleId}", [
            'scheduled_date' => $newDate,
        ])->assertOk()->assertJsonPath('data.scheduled_date', $newDate);
        $this->assertDatabaseHas('sms_notifications', [
            'vaccination_schedule_id' => $scheduleId,
            'status' => 'PENDING',
            'scheduled_date' => $newDate,
        ]);

        $otherBhw = $this->makeBhw('Other Inventory API Station', 'other.inventory.bhw');
        $otherToken = $otherBhw->createToken('other-inventory-client')->plainTextToken;
        $this->app['auth']->forgetGuards();
        $this->withToken($otherToken)->getJson('/api/v1/inventory')->assertOk()->assertJsonCount(0, 'data');
        $this->withToken($otherToken)->postJson('/api/v1/inventory', [
            'vaccine_id' => $vaccine->id,
            'quantity' => 99,
            'reorder_level' => 1,
            'health_station_id' => $otherBhw->health_station_id,
        ])->assertForbidden();
        $this->withToken($otherToken)->deleteJson("/api/v1/vaccination-schedules/{$scheduleId}")->assertForbidden();
        $this->assertDatabaseHas('vaccination_schedules', ['id' => $scheduleId]);

        $this->app['auth']->forgetGuards();
        $this->withToken($token)->deleteJson("/api/v1/vaccination-schedules/{$scheduleId}")
            ->assertOk()->assertJsonPath('message', 'Vaccination schedule deleted.');
        $this->assertDatabaseMissing('vaccination_schedules', ['id' => $scheduleId]);
    }

    public function test_cho_inventory_api_can_only_add_or_replenish_stock(): void
    {
        $station = HealthStation::create(['name' => 'CHO API Stock Station', 'barangay' => 'Central']);
        $cho = User::factory()->create(['role' => 'CHO_ADMIN', 'must_change_password' => false]);
        $token = $cho->createToken('cho-inventory-client')->plainTextToken;
        $vaccine = Vaccine::where('name', 'BCG')->firstOrFail();

        $response = $this->withToken($token)->postJson('/api/v1/inventory', [
            'vaccine_id' => $vaccine->id,
            'quantity' => 12,
            'reorder_level' => 3,
            'health_station_id' => $station->id,
        ])->assertCreated()->assertJsonPath('data.quantity', 12);
        $stockId = $response->json('data.id');

        $this->withToken($token)->postJson('/api/v1/inventory', [
            'vaccine_id' => $vaccine->id,
            'quantity' => 5,
            'reorder_level' => 99,
            'health_station_id' => $station->id,
        ])->assertOk()->assertJsonPath('data.quantity', 17);
        $this->withToken($token)->postJson("/api/v1/inventory/{$stockId}/adjust", [
            'action' => 'use',
            'quantity' => 1,
        ])->assertUnprocessable()->assertJsonValidationErrors('action');
        $this->withToken($token)->patchJson("/api/v1/inventory/{$stockId}", ['quantity' => 2])
            ->assertNotFound();
        $this->assertDatabaseHas('vaccine_inventory', [
            'id' => $stockId,
            'quantity' => 17,
            'reorder_level' => 3,
        ]);
    }

    public function test_cho_cannot_access_patient_endpoints_but_can_maintain_vaccine_catalog(): void
    {
        $cho = User::factory()->create([
            'role' => 'CHO_ADMIN',
            'account_status' => 'ACTIVE',
            'must_change_password' => false,
        ]);
        $token = $cho->createToken('cho-client')->plainTextToken;
        $this->withToken($token)->getJson('/api/v1/patients')->assertForbidden();
        $this->withToken($token)->postJson('/api/v1/patients')->assertForbidden();

        $vaccineResponse = $this->withToken($token)->postJson('/api/v1/vaccines', [
            'name' => 'Typhoid',
            'description' => 'Imported from approved catalog.',
        ])->assertCreated();
        $vaccineId = $vaccineResponse->json('data.id');
        $this->withToken($token)->patchJson("/api/v1/vaccines/{$vaccineId}", ['name' => 'Typhoid Vaccine'])
            ->assertOk()->assertJsonPath('data.name', 'Typhoid Vaccine');
    }

    public function test_bhw_can_read_and_update_guardians_and_create_a_patient_from_guardian_id(): void
    {
        $this->seedBarangays();
        [$bhw, $token] = $this->makeBhwAndToken('Guardian Station');

        $guardian = $this->withToken($token)->postJson('/api/v1/guardians', [
            'first_name' => 'Ana',
            'last_name' => 'Reyes',
            'relationship' => 'Mother',
            'contact_number' => '09171234567',
        ])->assertCreated();
        $guardianId = $guardian->json('data.id');

        $payload = $this->patientPayload();
        unset($payload['guardian']);
        $payload['guardian_id'] = $guardianId;
        $created = $this->withToken($token)->postJson('/api/v1/patients', $payload)->assertCreated();
        $this->assertDatabaseHas('children', [
            'id' => $created->json('data.id'),
            'guardian_id' => $guardianId,
            'health_station_id' => $bhw->health_station_id,
        ]);

        $this->withToken($token)->patchJson("/api/v1/guardians/{$guardianId}", ['occupation' => 'Nurse'])
            ->assertOk()->assertJsonPath('data.occupation', 'Nurse');
    }

    public function test_cho_historical_csv_import_tracks_sources_and_reports_unmapped_rows(): void
    {
        $this->seedBarangays();
        $cho = User::factory()->create([
            'role' => 'CHO_ADMIN',
            'account_status' => 'ACTIVE',
            'must_change_password' => false,
        ]);
        $token = $cho->createToken('import-client')->plainTextToken;
        $csv = implode("\n", [
            'month,barangay,metric_type,vaccine,disease,value',
            '2025-01,Barangay 1,IMMUNIZATION_DOSES,BCG,,12',
            '2025-02,Unknown Barangay,DISEASE_INCIDENTS,,Measles,3',
            '2025-03,Barangay 1,DISEASE_INCIDENTS,,Measles,2',
        ]);

        $response = $this->withToken($token)->postJson('/api/v1/historical-imports', [
            'source_name' => 'Authorized city health office monthly report',
            'source_reference' => 'CHO-2025-Q1',
            'file' => UploadedFile::fake()->createWithContent('history.csv', $csv),
        ])->assertCreated()->assertJsonPath('data.status', 'PARTIAL')
            ->assertJsonPath('data.accepted_rows', 2)
            ->assertJsonPath('data.rejected_rows', 1);

        $batchId = $response->json('data.id');
        $this->assertDatabaseCount('historical_indicators', 2);
        $this->assertDatabaseHas('historical_indicators', [
            'source_name' => 'Authorized city health office monthly report',
            'source_reference' => 'CHO-2025-Q1',
            'metric_type' => 'IMMUNIZATION_DOSES',
            'value' => 12,
        ]);
        $this->assertSame(
            '2025-01-01',
            HistoricalIndicator::where('source_reference', 'CHO-2025-Q1')->where('metric_type', 'IMMUNIZATION_DOSES')->firstOrFail()->period_start->toDateString()
        );
        $this->withToken($token)->getJson("/api/v1/historical-imports/{$batchId}")
            ->assertOk()->assertJsonPath('data.rejected_rows', 1)
            ->assertJsonPath('rows.data.1.status', 'REJECTED');

        [$bhw, $bhwToken] = $this->makeBhwAndToken('Import Station');
        $this->app['auth']->forgetGuards();
        $this->withToken($bhwToken)->getJson('/api/v1/historical-imports')->assertForbidden();
        $this->app['auth']->forgetGuards();
        $this->withToken($bhwToken)->postJson('/api/v1/historical-imports', [
            'source_name' => 'Not authorized',
            'file' => UploadedFile::fake()->createWithContent('history.csv', $csv),
        ])->assertForbidden();
    }

    private function makeBhwAndToken(string $stationName): array
    {
        $bhw = $this->makeBhw($stationName);

        return [$bhw, $bhw->createToken('test-client')->plainTextToken];
    }

    private function makeBhw(string $stationName, string $username = 'test.bhw'): User
    {
        $station = HealthStation::firstOrCreate(['name' => $stationName], ['barangay' => $stationName]);

        return User::factory()->create([
            'username' => $username,
            'role' => 'BHW',
            'account_status' => 'ACTIVE',
            'must_change_password' => false,
            'health_station_id' => $station->id,
        ]);
    }

    private function seedBarangays(): void
    {
        DB::table('barangays')->insertOrIgnore([
            'official_name' => 'Barangay 1',
            'name' => 'Libertad',
            'city' => 'Tacloban City',
            'province' => 'Leyte',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function patientPayload(array $overrides = []): array
    {
        return array_replace_recursive([
            'first_name' => 'Mia',
            'last_name' => 'Reyes',
            'sex' => 'Female',
            'date_of_birth' => today()->subMonths(3)->toDateString(),
            'barangay' => 'Barangay 1',
            'guardian' => [
                'first_name' => 'Ana',
                'last_name' => 'Reyes',
                'relationship' => 'Mother',
                'contact_number' => '09171234567',
            ],
        ], $overrides);
    }
}
