<?php

declare(strict_types=1);

namespace App\Support\Sms;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * No-provider fallback: writes the SMS to the log so local dev never needs a
 * paid gateway. While this client is bound, the OTP endpoint may also expose
 * the code to the dev UI (see OtpDebugExposure).
 */
final class LogSmsClient implements SmsClient
{
    public function send(string $to, string $body): string
    {
        Log::info("[SMS:log] to {$to}: {$body}");

        return 'log-'.Str::uuid()->toString();
    }
}
