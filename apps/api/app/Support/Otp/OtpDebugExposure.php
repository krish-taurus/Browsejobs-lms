<?php

declare(strict_types=1);

namespace App\Support\Otp;

use App\Enums\OtpChannel;
use App\Support\Sms\SmsConfig;

/**
 * Decides whether the OTP endpoint may echo the plaintext code back to the
 * client so local dev can sign in without any messaging gateway. Hard gate:
 * only with APP_DEBUG on AND no real integration configured for the channel —
 * the moment SMS/WhatsApp (or a real mailer) is set up, exposure stops.
 */
final class OtpDebugExposure
{
    public static function shouldExpose(OtpChannel $channel): bool
    {
        if (! (bool) config('app.debug')) {
            return false;
        }

        if ($channel === OtpChannel::Email) {
            return in_array(config('mail.default'), ['log', 'array'], true);
        }

        return ! SmsConfig::configured()
            && (string) config('services.whatsapp.access_token') === '';
    }
}
