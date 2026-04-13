<?php

declare(strict_types=1);

namespace App\Notifications\Channels;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * A generic SMS notification channel that supports Twilio, Vonage, or any HTTP SMS provider.
 * Configure via config/services.php under the 'sms' key.
 */
final class SmsChannel
{
    public function send(object $notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toSms')) {
            return;
        }

        $phoneNumber = $notifiable->phone_number ?? null;
        if ($phoneNumber === null || $phoneNumber === '') {
            return;
        }

        if (property_exists($notifiable, 'sms_notifications_enabled') && ! $notifiable->sms_notifications_enabled) {
            return;
        }

        $message = $notification->toSms($notifiable);
        if ($message === null || $message === '') {
            return;
        }

        $provider = config('services.sms.provider', 'log');

        match ($provider) {
            'twilio' => $this->sendViaTwilio($phoneNumber, $message),
            'vonage' => $this->sendViaVonage($phoneNumber, $message),
            default => $this->logSms($phoneNumber, $message),
        };
    }

    private function sendViaTwilio(string $to, string $message): void
    {
        $accountSid = config('services.twilio.account_sid');
        $authToken = config('services.twilio.auth_token');
        $from = config('services.twilio.from');

        Http::withBasicAuth($accountSid, $authToken)
            ->asForm()
            ->post("https://api.twilio.com/2010-04-01/Accounts/{$accountSid}/Messages.json", [
                'To' => $to,
                'From' => $from,
                'Body' => $message,
            ]);
    }

    private function sendViaVonage(string $to, string $message): void
    {
        Http::post('https://rest.nexmo.com/sms/json', [
            'api_key' => config('services.vonage.key'),
            'api_secret' => config('services.vonage.secret'),
            'to' => mb_ltrim($to, '+'),
            'from' => config('services.vonage.sms_from', 'Store'),
            'text' => $message,
        ]);
    }

    private function logSms(string $to, string $message): void
    {
        Log::channel('stack')->info("[SMS] To: {$to} | Message: {$message}");
    }
}
