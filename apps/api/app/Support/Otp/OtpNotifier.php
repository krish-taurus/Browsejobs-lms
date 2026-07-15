<?php

declare(strict_types=1);

namespace App\Support\Otp;

use App\Enums\OtpChannel;
use App\Enums\OtpPurpose;

/**
 * Delivers OTP codes over a channel. Real SMS/email adapters replace the
 * default log driver later (P2.4 messaging hub) without touching callers.
 */
interface OtpNotifier
{
    public function send(string $identifier, OtpChannel $channel, string $code, OtpPurpose $purpose): void;
}
