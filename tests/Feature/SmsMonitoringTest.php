<?php

namespace Tests\Feature;

use App\Models\Child;
use App\Models\Guardian;
use App\Models\HealthStation;
use App\Models\SmsNotification;
use App\Models\User;
use App\Models\VaccinationSchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SmsMonitoringTest extends TestCase
{
    use RefreshDatabase;

    public function test_sms_reminders_are_grouped_and_filtered_by_workflow_tab(): void
    {
        [$user, $child] = $this->makeBhwAndChild();
        $this->createScheduleWithReminders($child, 'Sent Vaccine', today()->addDays(21), [
            7 => 'SENT',
            3 => 'SENT',
            1 => 'SENT',
        ]);
        $this->createScheduleWithReminders($child, 'Overdue Vaccine', today()->subDays(2), [
            7 => 'FAILED',
            3 => 'PENDING',
            1 => 'SENT',
        ]);
        $this->createScheduleWithReminders($child, 'Future Vaccine', today()->addDays(9), [
            7 => 'PENDING',
            3 => 'PENDING',
            1 => 'PENDING',
        ]);
        $this->createScheduleWithReminders($child, 'Soon Vaccine', today()->addDays(7), [
            7 => 'PENDING',
            3 => 'PENDING',
            1 => 'PENDING',
        ]);

        $this->actingAs($user)
            ->get(route('sms.index'))
            ->assertOk()
            ->assertSee('Due Soon — Next 7 Days', false)
            ->assertSee('Showing 1–2 of 2')
            ->assertSeeInOrder(['Overdue Vaccine', 'Soon Vaccine'])
            ->assertSee('Overdue')
            ->assertSee('In 7 days')
            ->assertSee('7d: FAILED')
            ->assertSee('3d: PENDING')
            ->assertDontSee('Future Vaccine')
            ->assertDontSee('Sent Vaccine')
            ->assertSee('data-sms-bulk-send-form', false);

        $this->get(route('sms.index', ['tab' => 'all']))
            ->assertOk()
            ->assertSee('Showing 1–4 of 4');

        $this->get(route('sms.index', ['tab' => 'sent']))
            ->assertOk()
            ->assertSee('Sent Vaccine')
            ->assertDontSee('Overdue Vaccine')
            ->assertDontSee('Future Vaccine');

        $this->get(route('sms.index', ['tab' => 'failed']))
            ->assertOk()
            ->assertSee('Overdue Vaccine')
            ->assertDontSee('Sent Vaccine');

        $this->get(route('sms.index', ['tab' => 'all', 'status' => 'FAILED', 'reminder' => 7]))
            ->assertOk()
            ->assertSee('Overdue Vaccine')
            ->assertDontSee('Soon Vaccine')
            ->assertDontSee('Sent Vaccine');
    }

    public function test_sms_search_matches_child_guardian_contact_and_vaccine_fields(): void
    {
        [$user, $child] = $this->makeBhwAndChild(
            'Talia',
            'Rivera',
            'Luz',
            'Bautista',
            '09175550123'
        );
        $this->createScheduleWithReminders($child, 'Measles Rubella', today()->addDay());

        foreach (['Talia', 'Rivera', $child->baby_book_id, 'Luz', 'Bautista', '09175550123', 'Measles Rubella'] as $term) {
            $this->actingAs($user)
                ->get(route('sms.index', ['tab' => 'all', 'search' => $term]))
                ->assertOk()
                ->assertSee('Measles Rubella');
        }

        $this->get(route('sms.index', ['tab' => 'all', 'search' => 'No matching record']))
            ->assertOk()
            ->assertSee('No reminders found')
            ->assertDontSee('Measles Rubella');
    }

    public function test_sms_paginates_grouped_doses_and_preserves_filters(): void
    {
        [$user, $child] = $this->makeBhwAndChild();

        for ($index = 1; $index <= 16; $index++) {
            $this->createScheduleWithReminders(
                $child,
                'Pagination Vaccine '.$index,
                today()->addDays($index)
            );
        }

        $this->actingAs($user)
            ->get(route('sms.index', ['tab' => 'all', 'search' => 'Maria']))
            ->assertOk()
            ->assertSee('Showing 1–15 of 16')
            ->assertSee('search=Maria', false)
            ->assertSee('page=2', false);

        $this->get(route('sms.index', ['tab' => 'all', 'search' => 'Maria', 'page' => 2]))
            ->assertOk()
            ->assertSee('Showing 16–16 of 16')
            ->assertSee('Pagination Vaccine 16');
    }

    public function test_sms_uses_child_and_date_fallback_when_schedule_id_is_null(): void
    {
        [$user, $child] = $this->makeBhwAndChild();
        foreach ([7, 3] as $days) {
            SmsNotification::create([
                'child_id' => $child->id,
                'vaccination_schedule_id' => null,
                'reminder_days' => $days,
                'scheduled_date' => today()->addDays(2),
                'status' => 'PENDING',
            ]);
        }
        SmsNotification::create([
            'child_id' => $child->id,
            'vaccination_schedule_id' => null,
            'reminder_days' => 1,
            'scheduled_date' => today()->addDays(3),
            'status' => 'PENDING',
        ]);

        $this->actingAs($user)
            ->get(route('sms.index', ['tab' => 'all']))
            ->assertOk()
            ->assertSee('Showing 1–2 of 2');
    }

    public function test_sms_updates_support_json_redirect_and_bulk_next_pending_status(): void
    {
        [$user, $child] = $this->makeBhwAndChild();
        [, $reminders] = $this->createScheduleWithReminders($child, 'BCG', today()->addDays(4));
        $otherStation = HealthStation::create(['name' => 'Other SMS Station', 'barangay' => 'Other']);
        [, $otherChild] = $this->makeChildAtStation($otherStation);
        [, $otherReminders] = $this->createScheduleWithReminders($otherChild, 'Other Vaccine', today()->addDays(4));

        $this->actingAs($user)
            ->patchJson(route('sms.update', $reminders[3]), ['status' => 'SENT'])
            ->assertOk()
            ->assertJsonStructure(['message', 'notification' => ['id', 'status']])
            ->assertJsonPath('notification.id', $reminders[3]->id)
            ->assertJsonPath('notification.status', 'SENT');

        $this->from(route('sms.index', ['tab' => 'all', 'page' => 2]))
            ->patch(route('sms.update', $reminders[1]), ['status' => 'FAILED'])
            ->assertRedirect(route('sms.index', ['tab' => 'all', 'page' => 2]));
        $this->assertDatabaseHas('sms_notifications', ['id' => $reminders[1]->id, 'status' => 'FAILED']);

        $this->patchJson(route('sms.bulk-update'), ['notification_ids' => [$reminders[1]->id]])
            ->assertOk()
            ->assertJsonPath('notifications.0.id', $reminders[7]->id)
            ->assertJsonPath('notifications.0.status', 'SENT');
        $this->assertDatabaseHas('sms_notifications', ['id' => $reminders[7]->id, 'status' => 'SENT']);
        $this->assertDatabaseHas('sms_notifications', ['id' => $reminders[3]->id, 'status' => 'SENT']);

        $this->patchJson(route('sms.update', $otherReminders[7]), ['status' => 'SENT'])
            ->assertForbidden();
        $this->patchJson(route('sms.bulk-update'), ['notification_ids' => [$otherReminders[7]->id]])
            ->assertForbidden();
    }

    public function test_bhw_can_manually_send_a_reminder_through_skysms(): void
    {
        config([
            'services.skysms.base_url' => 'https://skysms.example/api/v1',
            'services.skysms.api_key' => 'test-api-key',
        ]);
        Http::fake(['*' => Http::response(['success' => true, 'unique_message_id' => 'msg_1', 'queue_id' => 1], 201)]);
        [$user, $child] = $this->makeBhwAndChild();
        [, $reminders] = $this->createScheduleWithReminders($child, 'BCG', today()->addDays(4));

        $this->actingAs($user)
            ->postJson(route('sms.send', $reminders[7]))
            ->assertOk()
            ->assertJsonPath('notification.status', 'SENT')
            ->assertJsonPath('notification.id', $reminders[7]->id);

        $this->assertDatabaseHas('sms_notifications', [
            'id' => $reminders[7]->id,
            'status' => 'SENT',
            'failure_reason' => null,
        ]);
        Http::assertSent(fn (ClientRequest $request) => $request['phone_number'] === '+639171234567');
    }

    public function test_bulk_send_checks_all_station_scopes_before_dispatching_any_sms(): void
    {
        config([
            'services.skysms.base_url' => 'https://skysms.example/api/v1',
            'services.skysms.api_key' => 'test-api-key',
        ]);
        Http::fake(['*' => Http::response(['success' => true], 201)]);
        [$user, $child] = $this->makeBhwAndChild();
        [, $reminders] = $this->createScheduleWithReminders($child, 'BCG', today()->addDays(4));
        $otherStation = HealthStation::create(['name' => 'Other SMS Station', 'barangay' => 'Other']);
        [, $otherChild] = $this->makeChildAtStation($otherStation);
        [, $otherReminders] = $this->createScheduleWithReminders($otherChild, 'Other Vaccine', today()->addDays(4));

        $this->actingAs($user)
            ->postJson(route('sms.bulk-send'), [
                'notification_ids' => [$reminders[7]->id, $otherReminders[7]->id],
            ])
            ->assertForbidden();

        Http::assertNothingSent();
    }

    public function test_bulk_send_returns_a_sent_and_failed_summary_for_selected_stages(): void
    {
        config([
            'services.skysms.base_url' => 'https://skysms.example/api/v1',
            'services.skysms.api_key' => 'test-api-key',
        ]);
        Http::fake([
            '*/sms/send' => Http::sequence()
                ->push(['success' => true, 'unique_message_id' => 'msg_ok', 'queue_id' => 1], 201)
                ->push(['success' => false, 'message' => 'Service unavailable'], 503),
        ]);
        [$user, $child] = $this->makeBhwAndChild();
        [, $reminders] = $this->createScheduleWithReminders($child, 'BCG', today()->addDays(4));

        $this->actingAs($user)
            ->postJson(route('sms.bulk-send'), [
                'notification_ids' => [$reminders[7]->id, $reminders[3]->id],
            ])
            ->assertOk()
            ->assertJsonPath('sent', 1)
            ->assertJsonPath('failed', 1)
            ->assertJsonPath('notifications.0.status', 'SENT')
            ->assertJsonPath('notifications.1.status', 'FAILED');

        Http::assertSentCount(2);
    }

    public function test_database_only_status_overrides_never_dispatch_sms(): void
    {
        Http::fake();
        [$user, $child] = $this->makeBhwAndChild();
        [, $reminders] = $this->createScheduleWithReminders($child, 'BCG', today()->addDays(4));

        $this->actingAs($user)
            ->patchJson(route('sms.update', $reminders[7]), ['status' => 'SENT'])
            ->assertOk();

        Http::assertNothingSent();
    }

    private function makeBhwAndChild(
        string $childFirstName = 'Mia',
        string $childLastName = 'Santos',
        string $guardianFirstName = 'Maria',
        string $guardianLastName = 'Santos',
        string $contactNumber = '09171234567'
    ): array {
        $station = HealthStation::create(['name' => 'SMS Test Station', 'barangay' => 'North']);
        $user = User::factory()->create([
            'role' => 'BHW',
            'health_station_id' => $station->id,
            'must_change_password' => false,
        ]);
        $guardian = Guardian::create([
            'first_name' => $guardianFirstName,
            'last_name' => $guardianLastName,
            'relationship' => 'Mother',
            'contact_number' => $contactNumber,
        ]);
        $child = Child::create([
            'guardian_id' => $guardian->id,
            'health_station_id' => $station->id,
            'first_name' => $childFirstName,
            'last_name' => $childLastName,
            'sex' => 'Female',
            'date_of_birth' => today()->subMonths(4)->toDateString(),
            'barangay' => 'North',
            'status' => 'ACTIVE',
        ]);

        return [$user, $child, $station];
    }

    private function makeChildAtStation(HealthStation $station): array
    {
        $guardian = Guardian::create([
            'first_name' => 'Other',
            'last_name' => 'Guardian',
            'relationship' => 'Mother',
            'contact_number' => '09170000000',
        ]);
        $child = Child::create([
            'guardian_id' => $guardian->id,
            'health_station_id' => $station->id,
            'first_name' => 'Other',
            'last_name' => 'Child',
            'sex' => 'Male',
            'date_of_birth' => today()->subMonths(4)->toDateString(),
            'barangay' => 'Other',
            'status' => 'ACTIVE',
        ]);

        return [$guardian, $child];
    }

    private function createScheduleWithReminders(Child $child, string $vaccine, $scheduledDate, array $statuses = []): array
    {
        $schedule = VaccinationSchedule::create([
            'child_id' => $child->id,
            'vaccine' => $vaccine,
            'dose_number' => 1,
            'scheduled_date' => $scheduledDate,
        ]);
        $reminders = [];
        foreach ([7, 3, 1] as $days) {
            $reminders[$days] = SmsNotification::create([
                'child_id' => $child->id,
                'vaccination_schedule_id' => $schedule->id,
                'reminder_days' => $days,
                'scheduled_date' => $scheduledDate,
                'status' => $statuses[$days] ?? 'PENDING',
            ]);
        }

        return [$schedule, $reminders];
    }
}
