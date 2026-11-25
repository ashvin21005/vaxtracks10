<?php

namespace App\Services;

use App\Models\SmsNotification;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class SkySmsService
{
    public function sendNotification(SmsNotification $notification): SmsNotification
    {
        if ($notification->status === 'FAILED' && (! empty($notification->gateway_message_id) || ! empty($notification->gateway_queue_id))) {
            return $this->resendNotification($notification);
        }

        $notification->loadMissing(['child.guardian', 'child.healthStation', 'schedule']);
        $guardian = $notification->child?->guardian;
        $contactNumber = trim((string) $guardian?->contact_number);

        if (! $guardian || $contactNumber === '') {
            return $this->markFailed($notification, 'No guardian contact number is available.');
        }

        $phoneNumber = $this->normalizePhoneNumber($contactNumber);
        if ($phoneNumber === null) {
            return $this->markFailed($notification, 'The guardian contact number is not a valid Philippine mobile number.');
        }

        return $this->sendSmsPayload($notification, $phoneNumber);
    }

    public function resendNotification(SmsNotification $notification): SmsNotification
    {
        $notification->loadMissing(['child.guardian', 'child.healthStation', 'schedule']);

        $baseUrl = rtrim((string) config('services.skysms.base_url'), '/');
        $apiKey = config('services.skysms.api_key');
        if ($baseUrl === '' || ! is_string($apiKey) || trim($apiKey) === '') {
            return $this->markFailed($notification, 'SkySMS is not configured with a base URL and API key.');
        }
        $apiKey = trim($apiKey);

        if (empty($notification->gateway_message_id) && empty($notification->gateway_queue_id)) {
            $notification->update([
                'gateway_message_id' => null,
                'gateway_queue_id' => null,
            ]);
            $notification = $notification->refresh();

            $guardian = $notification->child?->guardian;
            $contactNumber = trim((string) $guardian?->contact_number);
            if (! $guardian || $contactNumber === '') {
                return $this->markFailed($notification, 'No guardian contact number is available.');
            }

            $phoneNumber = $this->normalizePhoneNumber($contactNumber);
            if ($phoneNumber === null) {
                return $this->markFailed($notification, 'The guardian contact number is not a valid Philippine mobile number.');
            }

            return $this->sendSmsPayload($notification, $phoneNumber, $baseUrl, $apiKey);
        }

        try {
            if (! empty($notification->gateway_message_id)) {
                $response = Http::timeout(15)
                    ->withHeaders([
                        'X-API-Key' => $apiKey,
                        'Accept' => 'application/json',
                    ])
                    ->post($baseUrl.'/sms/messages/resend', [
                        'unique_message_id' => $notification->gateway_message_id,
                    ]);
            } else {
                $response = Http::timeout(15)
                    ->withHeaders([
                        'X-API-Key' => $apiKey,
                        'Accept' => 'application/json',
                    ])
                    ->post($baseUrl.'/sms/messages/'.$notification->gateway_queue_id.'/resend');
            }
        } catch (Throwable $exception) {
            Log::error('SkySMS resend request failed.', [
                'sms_notification_id' => $notification->id,
                'exception' => $exception->getMessage(),
            ]);

            return $this->markFailed($notification, 'SkySMS resend request failed: '.$exception->getMessage());
        }

        return $this->handleResponse($notification, $response);
    }

    public function buildReminderMessage(SmsNotification $notification): string
    {
        $notification->loadMissing(['child.guardian', 'child.healthStation', 'schedule']);
        $guardianName = $notification->child?->guardian?->full_name ?? 'Guardian';
        $patientName = $notification->child?->full_name ?? 'your child';
        $stationName = $notification->child?->healthStation?->name ?? 'health station';
        $schedule = $notification->schedule;
        $vaccine = $schedule?->vaccine ?? 'vaccination';
        $vaccineFull = $vaccine.' Dose '.($schedule?->dose_number ?? 'N/A');
        $scheduleDate = $notification->scheduled_date?->format('M d, Y') ?? 'the scheduled date';
        $days = (int) $notification->reminder_days;

        $buildMessage = function (bool $includeStation) use ($days, $guardianName, $patientName, $vaccineFull, $scheduleDate, $stationName): string {
            $stationSuffix = $includeStation ? " ({$stationName})" : '';

            return match ($days) {
                7 => "VaxTrack 7-Day Reminder: {$guardianName}, {$patientName} is due for {$vaccineFull} on {$scheduleDate}{$stationSuffix}. Bring Baby Book.",
                3 => "VaxTrack 3-Day Reminder: {$guardianName}, {$patientName} is due in 3d ({$scheduleDate}) for {$vaccineFull}{$stationSuffix}. Bring Baby Book.",
                1 => "VaxTrack 1-Day Reminder: {$guardianName}, {$patientName} is due tomorrow ({$scheduleDate}) for {$vaccineFull}{$stationSuffix}. Bring Baby Book.",
                default => "VaxTrack {$days}-Day Reminder: {$guardianName}, {$patientName} is due on {$scheduleDate} for {$vaccineFull}{$stationSuffix}. Bring Baby Book.",
            };
        };

        $message = $buildMessage(true);
        if (mb_strlen($message) > 160) {
            $message = $buildMessage(false);
        }

        if (mb_strlen($message) > 160) {
            $message = mb_strimwidth($message, 0, 160, '...');
        }

        return $message;
    }

    private function sendSmsPayload(SmsNotification $notification, string $phoneNumber, ?string $baseUrl = null, ?string $apiKey = null): SmsNotification
    {
        $baseUrl ??= rtrim((string) config('services.skysms.base_url'), '/');
        $apiKey ??= config('services.skysms.api_key');
        if ($baseUrl === '' || ! is_string($apiKey) || trim($apiKey) === '') {
            return $this->markFailed($notification, 'SkySMS is not configured with a base URL and API key.');
        }
        $apiKey = trim($apiKey);

        $message = $this->buildReminderMessage($notification);

        try {
            $response = Http::timeout(15)
                ->withHeaders([
                    'X-API-Key' => $apiKey,
                    'Accept' => 'application/json',
                ])
                ->post($baseUrl.'/sms/send', [
                    'phone_number' => $phoneNumber,
                    'message' => $message,
                    'priority' => 'high',
                    'use_subscription' => false,
                    'metadata' => json_encode([
                        'notification_id' => $notification->id,
                        'reminder_days' => $notification->reminder_days,
                    ]),
                ]);
        } catch (Throwable $exception) {
            Log::error('SkySMS request failed.', [
                'sms_notification_id' => $notification->id,
                'exception' => $exception->getMessage(),
            ]);

            return $this->markFailed($notification, 'SkySMS request failed: '.$exception->getMessage());
        }

        return $this->handleResponse($notification, $response);
    }

    private function handleResponse(SmsNotification $notification, Response $response): SmsNotification
    {
        $responseBody = $response->json();
        $gatewayMessageId = data_get($responseBody, 'unique_message_id');
        $gatewayQueueId = data_get($responseBody, 'queue_id');
        $gatewayMessageId = is_scalar($gatewayMessageId) && (string) $gatewayMessageId !== '' ? (string) $gatewayMessageId : null;
        $gatewayQueueId = is_scalar($gatewayQueueId) && (string) $gatewayQueueId !== '' ? (string) $gatewayQueueId : null;

        if ($response->successful() && data_get($responseBody, 'success') !== false) {
            $notification->update([
                'gateway_message_id' => $gatewayMessageId ?? $notification->gateway_message_id,
                'gateway_queue_id' => $gatewayQueueId ?? $notification->gateway_queue_id,
                'status' => 'SENT',
                'sent_at' => now(),
                'failure_reason' => null,
            ]);

            return $notification->refresh();
        }

        $failureReason = $this->failureReason($response, $responseBody);
        Log::error('SkySMS request returned a failure.', [
            'sms_notification_id' => $notification->id,
            'http_status' => $response->status(),
            'failure_reason' => $failureReason,
        ]);

        $notification->update([
            'gateway_message_id' => $gatewayMessageId ?? $notification->gateway_message_id,
            'gateway_queue_id' => $gatewayQueueId ?? $notification->gateway_queue_id,
            'status' => 'FAILED',
            'sent_at' => null,
            'failure_reason' => mb_substr($failureReason, 0, 500, 'UTF-8'),
        ]);

        return $notification->refresh();
    }

    private function normalizePhoneNumber(string $phoneNumber): ?string
    {
        $digits = preg_replace('/[\s()+.-]+/', '', $phoneNumber);
        if ($digits === null) {
            return null;
        }

        if (preg_match('/^09\d{9}$/', $digits) === 1) {
            return '+63'.substr($digits, 1);
        }

        if (preg_match('/^9\d{9}$/', $digits) === 1) {
            return '+63'.$digits;
        }

        return null;
    }

    private function failureReason(Response $response, mixed $responseBody): string
    {
        foreach ([
            data_get($responseBody, 'message'),
            data_get($responseBody, 'error'),
        ] as $reason) {
            if (is_string($reason) && trim($reason) !== '') {
                return trim($reason);
            }

            if (is_numeric($reason)) {
                return (string) $reason;
            }

            if (is_array($reason) || is_object($reason)) {
                $encodedReason = json_encode($reason);
                if (is_string($encodedReason)) {
                    return $encodedReason;
                }
            }
        }

        return 'SkySMS returned HTTP '.$response->status().'.';
    }

    private function markFailed(SmsNotification $notification, string $reason): SmsNotification
    {
        $notification->update([
            'status' => 'FAILED',
            'sent_at' => null,
            'failure_reason' => mb_substr($reason, 0, 500, 'UTF-8'),
        ]);

        return $notification->refresh();
    }
}
