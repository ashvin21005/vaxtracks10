<?php

namespace Tests\Feature;

use App\Models\Child;
use App\Models\Guardian;
use App\Models\HealthStation;
use App\Models\ImmunizationRecord;
use App\Models\SmsNotification;
use App\Models\User;
use App\Models\VaccinationSchedule;
use App\Models\Vaccine;
use App\Models\VaccineInventory;
use App\Services\EpiScheduleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperationalWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_immunization_entry_uses_a_context_preserving_modal_and_confirmation(): void
    {
        [$user] = $this->makeBhwAndChild();
        $this->actingAs($user)
            ->get(route('immunizations.create'))
            ->assertRedirect(route('immunizations.index', ['record' => 1]));

        $this->get(route('immunizations.index', ['record' => 1]))
            ->assertOk()
            ->assertSee('immunization-record-dialog')
            ->assertSee('data-confirm-message="Confirm that this vaccine dose was administered.', false)
            ->assertSee('data-confirmation-dialog', false)
            ->assertSee('data-modal-success="record"', false);
    }

    public function test_immunization_records_are_paginated_and_keep_the_search_when_changing_pages(): void
    {
        [$user, $templateChild, $station] = $this->makeBhwAndChild();
        $vaccine = Vaccine::where('name', 'BCG')->firstOrFail();

        for ($index = 1; $index <= 16; $index++) {
            $child = Child::create([
                'guardian_id' => $templateChild->guardian_id,
                'health_station_id' => $station->id,
                'first_name' => 'Pagination',
                'last_name' => 'Child '.$index,
                'sex' => 'Female',
                'date_of_birth' => today()->subMonths(5)->toDateString(),
                'barangay' => $templateChild->barangay,
                'status' => 'ACTIVE',
            ]);
            ImmunizationRecord::create([
                'child_id' => $child->id,
                'vaccine_id' => $vaccine->id,
                'vaccine' => $vaccine->name,
                'dose_number' => 1,
                'administered_at' => today()->subDays($index),
            ]);
        }

        $this->actingAs($user)
            ->get(route('immunizations.index', ['search' => 'Pagination', 'per_page' => 10]))
            ->assertOk()
            ->assertSee('16 records')
            ->assertSee('Showing 1–10 of 16')
            ->assertSee('10 per page')
            ->assertSee('search=Pagination', false);

        $this->get(route('immunizations.index', ['search' => 'Pagination', 'per_page' => 10, 'page' => 2]))
            ->assertOk()
            ->assertSee('Pagination Child 16')
            ->assertSee('Showing 11–16 of 16');
    }

    public function test_generated_schedule_can_be_adjusted_and_recording_completes_dose(): void
    {
        [$user, $child, $station] = $this->makeBhwAndChild();
        $vaccine = Vaccine::where('name', 'BCG')->firstOrFail();
        $stock = VaccineInventory::create([
            'health_station_id' => $station->id,
            'vaccine_id' => $vaccine->id,
            'vaccine' => $vaccine->name,
            'quantity' => 3,
            'reorder_level' => 1,
        ]);
        $this->actingAs($user);
        app(EpiScheduleService::class)->syncChild($child);

        $schedule = VaccinationSchedule::where('child_id', $child->id)
            ->where('vaccine_id', $vaccine->id)
            ->where('dose_number', 1)
            ->firstOrFail();
        $this->assertTrue($schedule->generated_by_protocol);
        $this->assertSame(3, SmsNotification::where('vaccination_schedule_id', $schedule->id)->count());
        $this->get(route('schedule.index'))->assertOk()
            ->assertDontSee('Add a vaccination schedule')
            ->assertDontSee('Create schedule');
        $this->patch(route('schedule.update', $schedule), ['status' => 'MISSED'])
            ->assertRedirect(route('schedule.index'));
        $this->assertDatabaseHas('vaccination_schedules', ['id' => $schedule->id, 'status' => 'MISSED']);

        $this->post(route('immunizations.store'), [
            'child_id' => $child->id,
            'vaccine' => 'BCG',
            'dose_number' => 1,
            'administered_at' => today()->toDateString(),
        ])->assertRedirect(route('immunizations.index'));

        $this->assertDatabaseHas('immunization_records', [
            'child_id' => $child->id,
            'vaccine_id' => Vaccine::where('name', 'BCG')->value('id'),
            'recorded_by' => $user->id,
            'status' => 'COMPLETED',
        ]);
        $this->assertDatabaseHas('vaccination_schedules', ['id' => $schedule->id, 'status' => 'COMPLETED']);
        $this->assertDatabaseHas('vaccine_inventory', ['id' => $stock->id, 'quantity' => 2]);

        $record = ImmunizationRecord::firstOrFail();
        $secondBhw = User::factory()->create([
            'role' => 'BHW',
            'must_change_password' => false,
            'health_station_id' => $user->health_station_id,
        ]);
        $this->actingAs($secondBhw)->put(route('immunizations.update', $record), [
            'child_id' => $child->id,
            'vaccine' => 'BCG',
            'dose_number' => 1,
            'administered_at' => today()->toDateString(),
        ])->assertRedirect(route('immunizations.index'));
        $this->assertDatabaseHas('immunization_records', ['id' => $record->id, 'recorded_by' => $user->id]);
    }

    public function test_schedule_groups_coadministered_doses_and_requires_review_before_confirmation(): void
    {
        [$user, $child] = $this->makeBhwAndChild();
        $visitDate = today()->addDays(7);

        foreach (['BCG' => false, 'Hepatitis B' => true] as $vaccineName => $reviewRequired) {
            $vaccine = Vaccine::where('name', $vaccineName)->firstOrFail();
            VaccinationSchedule::create([
                'child_id' => $child->id,
                'vaccine_id' => $vaccine->id,
                'vaccine' => $vaccine->name,
                'dose_number' => 1,
                'scheduled_date' => $visitDate,
                'due_date' => $visitDate,
                'generated_by_protocol' => true,
                'review_required' => $reviewRequired,
            ]);
        }

        $response = $this->actingAs($user)
            ->get(route('schedule.index'))
            ->assertOk()
            ->assertSee('1 visits · 2 doses')
            ->assertSee('2 vaccine doses')
            ->assertSee('BCG')
            ->assertSee('Hepatitis B')
            ->assertSee('Dose history requires clinical review.')
            ->assertSee('Confirm vaccinated');

        $this->assertSame(1, substr_count($response->getContent(), 'Confirm vaccinated'));
    }

    public function test_web_immunization_recording_is_blocked_when_station_has_no_vaccine_stock(): void
    {
        [$user, $child] = $this->makeBhwAndChild();
        $this->actingAs($user)
            ->post(route('immunizations.store'), [
                'child_id' => $child->id,
                'vaccine' => 'BCG',
                'dose_number' => 1,
                'administered_at' => today()->toDateString(),
            ])
            ->assertSessionHasErrors('inventory');

        $this->assertDatabaseMissing('immunization_records', ['child_id' => $child->id]);
    }

    public function test_existing_schedule_date_can_be_updated_and_pending_reminders_follow_it(): void
    {
        [$user, $child] = $this->makeBhwAndChild();
        $schedule = VaccinationSchedule::create([
            'child_id' => $child->id,
            'vaccine' => 'BCG',
            'dose_number' => 1,
            'scheduled_date' => today()->addDays(5),
        ]);
        SmsNotification::create([
            'child_id' => $child->id,
            'vaccination_schedule_id' => $schedule->id,
            'reminder_days' => 7,
            'scheduled_date' => $schedule->scheduled_date,
            'status' => 'PENDING',
        ]);
        SmsNotification::create([
            'child_id' => $child->id,
            'vaccination_schedule_id' => $schedule->id,
            'reminder_days' => 3,
            'scheduled_date' => $schedule->scheduled_date,
            'status' => 'SENT',
        ]);

        $newDate = today()->addDays(12)->toDateString();
        $this->actingAs($user)
            ->get(route('schedule.index'))
            ->assertOk()
            ->assertSee('Change date')
            ->assertSee('schedule-actions', false);
        $this->patch(route('schedule.update', $schedule), ['scheduled_date' => $newDate])
            ->assertRedirect(route('schedule.index'));

        $this->assertSame($newDate, $schedule->fresh()->scheduled_date->toDateString());
        $this->assertSame($newDate, SmsNotification::where('vaccination_schedule_id', $schedule->id)
            ->where('reminder_days', 7)->firstOrFail()->scheduled_date->toDateString());
        $this->assertNotSame($newDate, SmsNotification::where('vaccination_schedule_id', $schedule->id)
            ->where('reminder_days', 3)->firstOrFail()->scheduled_date->toDateString());
    }

    public function test_bhw_can_remove_a_scheduled_dose_and_pending_reminders_but_keeps_sent_history(): void
    {
        [$user, $child] = $this->makeBhwAndChild();
        $schedule = VaccinationSchedule::create([
            'child_id' => $child->id,
            'vaccine' => 'BCG',
            'dose_number' => 1,
            'scheduled_date' => today()->addDays(5),
        ]);
        $pendingReminder = SmsNotification::create([
            'child_id' => $child->id,
            'vaccination_schedule_id' => $schedule->id,
            'reminder_days' => 3,
            'scheduled_date' => $schedule->scheduled_date,
            'status' => 'PENDING',
        ]);
        $sentReminder = SmsNotification::create([
            'child_id' => $child->id,
            'vaccination_schedule_id' => $schedule->id,
            'reminder_days' => 1,
            'scheduled_date' => $schedule->scheduled_date,
            'status' => 'SENT',
        ]);
        $record = ImmunizationRecord::create([
            'child_id' => $child->id,
            'vaccine' => 'BCG',
            'dose_number' => 1,
            'administered_at' => today(),
            'recorded_by' => $user->id,
        ]);
        $otherUser = User::factory()->create([
            'role' => 'BHW',
            'must_change_password' => false,
            'health_station_id' => HealthStation::create(['name' => 'Other Station', 'barangay' => 'Other'])->id,
        ]);

        $this->actingAs($otherUser)
            ->delete(route('schedule.destroy', $schedule))
            ->assertForbidden();
        $this->assertDatabaseHas('vaccination_schedules', ['id' => $schedule->id]);

        $this->actingAs($user)
            ->get(route('schedule.index'))
            ->assertOk()
            ->assertSee('Remove dose')
            ->assertSee('schedule-actions', false);
        $this->delete(route('schedule.destroy', $schedule))
            ->assertRedirect(route('schedule.index'));

        $this->assertDatabaseMissing('vaccination_schedules', ['id' => $schedule->id]);
        $this->assertDatabaseMissing('sms_notifications', ['id' => $pendingReminder->id]);
        $this->assertDatabaseHas('sms_notifications', [
            'id' => $sentReminder->id,
            'vaccination_schedule_id' => null,
            'status' => 'SENT',
        ]);
        $this->assertDatabaseHas('immunization_records', ['id' => $record->id]);
    }

    public function test_cho_cannot_remove_a_scheduled_dose(): void
    {
        [, $child] = $this->makeBhwAndChild();
        $cho = User::factory()->create(['role' => 'CHO_ADMIN', 'must_change_password' => false]);
        $schedule = VaccinationSchedule::create([
            'child_id' => $child->id,
            'vaccine' => 'BCG',
            'dose_number' => 1,
            'scheduled_date' => today()->addDays(5),
        ]);

        $this->actingAs($cho)
            ->delete(route('schedule.destroy', $schedule))
            ->assertForbidden();
        $this->assertDatabaseHas('vaccination_schedules', ['id' => $schedule->id]);
    }

    public function test_bhw_inventory_is_read_only_and_limited_to_their_station(): void
    {
        [$user, , $station] = $this->makeBhwAndChild();
        $stock = VaccineInventory::create([
            'health_station_id' => $station->id,
            'vaccine' => 'OPV',
            'quantity' => 5,
            'reorder_level' => 2,
        ]);
        $otherStation = HealthStation::create(['name' => 'Other Station', 'barangay' => 'Other Barangay']);
        $otherStock = VaccineInventory::create([
            'health_station_id' => $otherStation->id,
            'vaccine' => 'BCG',
            'quantity' => 5,
            'reorder_level' => 2,
        ]);
        $this->actingAs($user)
            ->get(route('inventory.index'))
            ->assertOk()
            ->assertSee('OPV')
            ->assertSee('5</strong> doses', false)
            ->assertDontSee('Other Station')
            ->assertDontSee('Replenish stock')
            ->assertDontSee('Stock controls')
            ->assertDontSee('Delete');

        $this->post('/cho/vaccine-inventory', [
            'vaccine' => 'BCG',
            'quantity' => 24,
            'reorder_level' => 5,
            'health_station_id' => $station->id,
        ])->assertForbidden();
        $this->post("/cho/vaccine-inventory/{$stock->id}/replenish", [
            'action' => 'add',
            'quantity' => 2,
        ])->assertForbidden();
        $this->assertDatabaseHas('vaccine_inventory', ['id' => $stock->id, 'quantity' => 5]);
        $this->assertDatabaseHas('vaccine_inventory', ['id' => $otherStock->id]);
    }

    public function test_cho_can_add_or_replenish_stock_but_cannot_manually_reduce_or_delete_it(): void
    {
        $cho = User::factory()->create(['role' => 'CHO_ADMIN', 'must_change_password' => false]);
        $station = HealthStation::create(['name' => 'CHO Inventory Station', 'barangay' => 'Central']);
        $vaccine = Vaccine::where('name', 'BCG')->firstOrFail();
        $stock = VaccineInventory::create([
            'health_station_id' => $station->id,
            'vaccine_id' => $vaccine->id,
            'vaccine' => $vaccine->name,
            'quantity' => 5,
            'reorder_level' => 2,
        ]);

        $this->actingAs($cho)->get(route('inventory.index'))
            ->assertOk()
            ->assertSee('Replenish stock')
            ->assertSee('Add stock')
            ->assertSee('Doses to add')
            ->assertDontSee('Delete')
            ->assertDontSee('− Use');
        $this->post(route('cho.inventory.adjust', $stock), [
            'action' => 'add',
            'quantity' => 4,
        ])->assertRedirect(route('inventory.index'));
        $this->assertDatabaseHas('vaccine_inventory', ['id' => $stock->id, 'quantity' => 9]);
        $this->post(route('cho.inventory.adjust', $stock), [
            'action' => 'use',
            'quantity' => 2,
        ])->assertSessionHasErrors('action');
        $this->delete("/cho/vaccine-inventory/{$stock->id}")->assertNotFound();
        $this->post(route('cho.inventory.store'), [
            'vaccine' => 'Pentavalent',
            'quantity' => 12,
            'reorder_level' => 3,
            'health_station_id' => $station->id,
        ])->assertRedirect(route('inventory.index'));
        $this->assertDatabaseHas('vaccine_inventory', [
            'health_station_id' => $station->id,
            'vaccine' => 'Pentavalent',
            'quantity' => 12,
        ]);
        $this->post(route('cho.inventory.store'), [
            'vaccine' => 'BCG',
            'quantity' => 3,
            'reorder_level' => 99,
            'health_station_id' => $station->id,
        ])->assertRedirect(route('inventory.index'));
        $this->assertDatabaseHas('vaccine_inventory', [
            'id' => $stock->id,
            'quantity' => 12,
            'reorder_level' => 2,
        ]);
        $this->assertDatabaseHas('vaccine_inventory', ['id' => $stock->id, 'quantity' => 12]);
        $this->assertDatabaseHas('vaccines', ['id' => $vaccine->id, 'name' => 'BCG']);
    }

    public function test_bhw_can_delete_an_immunization_record_and_reopen_its_schedule_only_at_their_station(): void
    {
        [$user, $child] = $this->makeBhwAndChild();
        $otherStation = HealthStation::create(['name' => 'Other Record Station', 'barangay' => 'Other']);
        $otherGuardian = Guardian::create([
            'first_name' => 'Other',
            'last_name' => 'Guardian',
            'relationship' => 'Mother',
            'contact_number' => '09171234568',
        ]);
        $otherChild = Child::create([
            'guardian_id' => $otherGuardian->id,
            'health_station_id' => $otherStation->id,
            'first_name' => 'Other',
            'last_name' => 'Child',
            'sex' => 'Male',
            'date_of_birth' => today()->subMonths(4),
            'barangay' => 'Other',
            'status' => 'ACTIVE',
        ]);
        $schedule = VaccinationSchedule::create([
            'child_id' => $child->id,
            'vaccine' => 'BCG',
            'dose_number' => 1,
            'scheduled_date' => today()->subDay(),
            'status' => 'COMPLETED',
        ]);
        $record = ImmunizationRecord::create([
            'child_id' => $child->id,
            'vaccine' => 'BCG',
            'dose_number' => 1,
            'administered_at' => today()->subDay(),
        ]);
        $otherRecord = ImmunizationRecord::create([
            'child_id' => $otherChild->id,
            'vaccine' => 'BCG',
            'dose_number' => 1,
            'administered_at' => today()->subDay(),
        ]);

        $this->actingAs($user)
            ->get(route('immunizations.index'))
            ->assertOk()
            ->assertSee('Actions')
            ->assertSee('Delete');

        $this->delete(route('immunizations.destroy', $otherRecord))->assertForbidden();
        $this->assertDatabaseHas('immunization_records', ['id' => $otherRecord->id]);

        $this->delete(route('immunizations.destroy', $record))
            ->assertRedirect(route('immunizations.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('immunization_records', ['id' => $record->id]);
        $this->assertDatabaseHas('vaccination_schedules', ['id' => $schedule->id, 'status' => 'UPCOMING']);
    }

    public function test_cho_can_manage_the_vaccine_master_list_and_retrieve_renamed_vaccines(): void
    {
        [, $child, $station] = $this->makeBhwAndChild();
        $cho = User::factory()->create(['role' => 'CHO_ADMIN', 'must_change_password' => false]);
        $vaccine = Vaccine::where('name', 'OPV')->firstOrFail();
        $stock = VaccineInventory::create([
            'health_station_id' => $station->id,
            'vaccine_id' => $vaccine->id,
            'vaccine' => $vaccine->name,
            'quantity' => 12,
            'reorder_level' => 3,
        ]);
        ImmunizationRecord::create([
            'child_id' => $child->id,
            'vaccine_id' => $vaccine->id,
            'vaccine' => $vaccine->name,
            'dose_number' => 1,
            'administered_at' => today(),
        ]);
        VaccinationSchedule::create([
            'child_id' => $child->id,
            'vaccine' => $vaccine->name,
            'dose_number' => 1,
            'scheduled_date' => today()->addWeek(),
        ]);

        $this->actingAs($cho)
            ->get(route('cho.vaccines.index'))
            ->assertOk()
            ->assertSee('OPV')
            ->assertSee('Vaccine master list');
        $this->get(route('cho.vaccines.create'))->assertOk()->assertSee('Vaccine name');
        $this->post(route('cho.vaccines.store'), [
            'name' => 'Rotavirus',
            'description' => 'Oral vaccine',
            'is_active' => '1',
        ])->assertRedirect(route('cho.vaccines.index'));
        $newVaccine = Vaccine::where('name', 'Rotavirus')->firstOrFail();
        $this->assertTrue($newVaccine->is_active);

        $this->get(route('cho.vaccines.edit', $vaccine))->assertOk()->assertSee('Edit vaccine');
        $this->put(route('cho.vaccines.update', $vaccine), [
            'name' => 'Oral Polio Vaccine',
            'description' => 'Updated catalog description',
            'is_active' => '1',
        ])->assertRedirect(route('cho.vaccines.index'));

        $this->assertDatabaseHas('vaccines', ['id' => $vaccine->id, 'name' => 'Oral Polio Vaccine']);
        $this->assertDatabaseHas('vaccine_inventory', ['id' => $stock->id, 'vaccine' => 'Oral Polio Vaccine']);
        $this->assertDatabaseHas('immunization_records', ['vaccine_id' => $vaccine->id, 'vaccine' => 'Oral Polio Vaccine']);
        $this->assertDatabaseHas('vaccination_schedules', ['vaccine' => 'Oral Polio Vaccine']);
        $this->get(route('inventory.index'))
            ->assertOk()
            ->assertSee('Oral Polio Vaccine')
            ->assertDontSee('Edit vaccine name');
    }

    public function test_bhw_cannot_access_or_edit_the_vaccine_master_list(): void
    {
        $bhw = User::factory()->create(['role' => 'BHW', 'must_change_password' => false]);
        $this->actingAs($bhw)
            ->get(route('cho.vaccines.index'))
            ->assertForbidden();

        $this->post(route('cho.vaccines.store'), [
            'name' => 'Unauthorized Vaccine',
            'is_active' => '1',
        ])->assertForbidden();

        $this->assertDatabaseMissing('vaccines', ['name' => 'Unauthorized Vaccine']);
    }

    private function makeBhwAndChild(): array
    {
        $station = HealthStation::create(['name' => 'North Health Station', 'barangay' => 'North']);
        $user = User::factory()->create([
            'role' => 'BHW',
            'account_status' => 'ACTIVE',
            'must_change_password' => false,
            'health_station_id' => $station->id,
        ]);
        $guardian = Guardian::create([
            'first_name' => 'Maria',
            'last_name' => 'Santos',
            'relationship' => 'Mother',
            'contact_number' => '09171234567',
        ]);
        $child = Child::create([
            'guardian_id' => $guardian->id,
            'health_station_id' => $station->id,
            'first_name' => 'Ana',
            'last_name' => 'Santos',
            'sex' => 'Female',
            'date_of_birth' => today()->subMonths(4)->toDateString(),
            'barangay' => 'North',
            'status' => 'ACTIVE',
        ]);

        return [$user, $child, $station];
    }
}
