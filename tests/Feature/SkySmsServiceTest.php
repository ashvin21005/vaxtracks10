<?php

namespace Tests\Feature;

use App\Models\Child;
use App\Models\Guardian;
use App\Models\HealthStation;
use App\Models\SmsNotification;
use App\Models\VaccinationSchedule;
use App\Services\SkySmsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class SkySmsServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.skysms.base_url' => 'https://skysms.example/api/v1',
            'services.skysms.api_key' => 'test-api-key',
        ]);
    }

    public function test_it_sends_a_single_manual_reminder_with_a_normalized_phone_number(): void
    {
        Http::fake(['*' => Http::response(['success' => true, 'unique_message_id' => 'msg_123', 'queue_id' => 77], 201)]);
        $notification = $this->makeNotification('09171234567');

        $result = app(SkySmsService::class)->sendNotification($notification);

        $this->assertSame('SENT', $result->status);
        $this->assertNotNull($result->sent_at);
        $this->assertNull($result->failure_reason);
        $this->assertSame('msg_123', $result->gateway_message_id);
        $this->assertSame('77', $result->gateway_queue_id);
        Http::assertSent(fn (ClientRequest $request) => $request->url() === 'https://skysms.example/api/v1/sms/send'
            && $request->hasHeader('X-API-Key', 'test-api-key')
            && $request->hasHeader('Accept', 'application/json')
            && $request['phone_number'] === '+639171234567'
            && $request['message'] === 'VaxTrack 7-Day Reminder: Maria Santos, Mia Santos is due for BCG Dose 1 on '.today()->addDays(4)->format('M d, Y').' (Central Health Station). Bring Baby Book.'
            && $request['priority'] === 'high'
            && $request['use_subscription'] === false
            && $request['metadata'] === json_encode([
                'notification_id' => $notification->id,
                'reminder_days' => 7,
            ]));
    }

    public function test_it_normalizes_ten_digit_mobile_numbers_and_builds_the_three_day_template(): void
    {
        Http::fake(['*' => Http::response(['success' => true], 201)]);
        $notification = $this->makeNotification('9171234567');
        $notification->update(['reminder_days' => 3]);

        $result = app(SkySmsService::class)->sendNotification($notification);

        $this->assertSame('SENT', $result->status);
        Http::assertSent(fn (ClientRequest $request) => $request['phone_number'] === '+639171234567'
            && $request['message'] === 'VaxTrack 3-Day Reminder: Maria Santos, Mia Santos is due in 3d ('.today()->addDays(4)->format('M d, Y').') for BCG Dose 1 (Central Health Station). Bring Baby Book.');
    }

    public function test_it_rejects_invalid_phone_numbers(): void
    {
        Http::fake(['*' => Http::response(['success' => true], 200)]);
        $invalidNumber = $this->makeNotification('555-0100');

        $invalidNumberResult = app(SkySmsService::class)->sendNotification($invalidNumber);

        $this->assertSame('FAILED', $invalidNumberResult->status);
        $this->assertStringContainsString('valid Philippine mobile', $invalidNumberResult->failure_reason);
        Http::assertNothingSent();
    }

    public function test_it_records_http_failures_and_logs_connection_exceptions(): void
    {
        Http::fake(['*' => Http::response(['success' => false, 'message' => 'Service unavailable', 'unique_message_id' => 'msg_fail', 'queue_id' => 88], 503)]);
        $failedResponse = app(SkySmsService::class)->sendNotification($this->makeNotification());
        $this->assertSame('FAILED', $failedResponse->status);
        $this->assertSame('Service unavailable', $failedResponse->failure_reason);
        $this->assertSame('msg_fail', $failedResponse->gateway_message_id);
        $this->assertSame('88', $failedResponse->gateway_queue_id);
        $this->assertNull($failedResponse->sent_at);

        Http::fake(fn () => throw new ConnectionException(str_repeat('x', 600)));
        Log::spy();
        $failedConnection = app(SkySmsService::class)->sendNotification($this->makeNotification());

        $this->assertSame('FAILED', $failedConnection->status);
        $this->assertSame(500, mb_strlen($failedConnection->failure_reason, 'UTF-8'));
        Log::shouldHaveReceived('error')->once();
    }

    public function test_it_resends_failed_messages_using_unique_message_id(): void
    {
        Http::fake(['*' => Http::response(['success' => true, 'unique_message_id' => 'msg_new', 'queue_id' => 99], 200)]);
        $notification = $this->makeNotification();
        $notification->update([
            'status' => 'FAILED',
            'failure_reason' => 'Previous failure',
            'gateway_message_id' => 'msg_old',
            'gateway_queue_id' => '10',
        ]);

        $result = app(SkySmsService::class)->sendNotification($notification);

        $this->assertSame('SENT', $result->status);
        $this->assertNull($result->failure_reason);
        $this->assertSame('msg_new', $result->gateway_message_id);
        $this->assertSame('99', $result->gateway_queue_id);
        Http::assertSent(fn (ClientRequest $request) => $request->url() === 'https://skysms.example/api/v1/sms/messages/resend'
            && $request->hasHeader('X-API-Key', 'test-api-key')
            && $request['unique_message_id'] === 'msg_old');
    }

    public function test_reminder_copy_is_formal_and_within_one_sms_segment(): void
    {
        $notification = $this->makeNotification(
            '09265222314',
            'Jonas Salinas',
            'Sarah Emano Salinas',
            'VNG Health Center'
        );
        $notification->schedule->update(['vaccine' => 'OPV']);

        $message = app(SkySmsService::class)->buildReminderMessage($notification);

        $this->assertStringContainsString('VaxTrack 7-Day Reminder: Jonas Salinas, Sarah Emano Salinas', $message);
        $this->assertStringContainsString('OPV Dose 1', $message);
        $this->assertStringContainsString('Bring Baby Book.', $message);
        $this->assertLessThanOrEqual(160, mb_strlen($message, 'UTF-8'));
    }

    public function test_long_messages_are_reduced_to_one_sms_segment(): void
    {
        $notification = $this->makeNotification(
            '09171234567',
            str_repeat('Guardian', 15),
            str_repeat('Patient', 15),
            str_repeat('Station', 15)
        );

        $message = app(SkySmsService::class)->buildReminderMessage($notification);

        $this->assertLessThanOrEqual(160, mb_strlen($message, 'UTF-8'));
        $this->assertStringEndsWith('...');
        $this->assertStringNotContainsString('StationStation', $message);
    }

    private function makeNotification(
        string $contactNumber = '09171234567',
        string $guardianName = 'Maria Santos',
        string $childName = 'Mia Santos',
        string $stationName = 'Central Health Station'
    ): SmsNotification {
        $station = HealthStation::firstOrCreate(
            ['name' => $stationName],
            ['barangay' => 'Central']
        );
        $guardian = Guardian::create([
            'first_name' => $guardianName,
            'last_name' => '',
            'relationship' => 'Mother',
            'contact_number' => $contactNumber,
        ]);
        $child = Child::create([
            'guardian_id' => $guardian->id,
            'health_station_id' => $station->id,
            'first_name' => $childName,
            'last_name' => '',
            'sex' => 'Female',
            'date_of_birth' => today()->subMonths(4)->toDateString(),
            'barangay' => 'Central',
            'status' => 'ACTIVE',
        ]);
        $schedule = VaccinationSchedule::create([
            'child_id' => $child->id,
            'vaccine' => 'BCG',
            'dose_number' => 1,
            'scheduled_date' => today()->addDays(4),
        ]);

        return SmsNotification::create([
            'child_id' => $child->id,
            'vaccination_schedule_id' => $schedule->id,
            'reminder_days' => 7,
            'scheduled_date' => today()->addDays(4),
            'status' => 'PENDING',
        ]);
    }
}
