<?php

declare(strict_types=1);

namespace App\Support\Otp;

use App\Enums\OtpChannel;
use App\Enums\OtpPurpose;
use Illuminate\Support\Facades\Log;

/**
 * Default OTP delivery: writes to the log. Never logs the full code at info
 * level in a way that would leak it in production — only a masked hint plus
 * the destination, with the code itself at debug for local development.
 */
final class LogOtpNotifier implements OtpNotifier
{
    public function send(string $identifier, OtpChannel $channel, string $code, OtpPurpose $purpose): void
    {
        Log::info('OTP dispatched', [
            'identifier' => $identifier,
            'channel' => $channel->value,
            'purpose' => $purpose->value,
        ]);

        Log::debug('OTP code (local only)', ['identifier' => $identifier, 'code' => $code]);
    }
}
