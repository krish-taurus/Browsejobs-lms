<?php

declare(strict_types=1);

namespace App\Support\Sms;

/**
 * Answers "is a real SMS gateway configured?" — the switch that routes
 * phone-channel OTPs over SMS and hides the dev-only code exposure.
 */
final class SmsConfig
{
    public static function configured(): bool
    {
        $config = config('services.sms');
        if (! is_array($config)) {
            return false;
        }

        return match ($config['provider'] ?? '') {
            'msg91' => ($config['msg91']['authkey'] ?? '') !== '' && ($config['msg91']['flow_id'] ?? '') !== '',
            'twilio' => ($config['twilio']['account_sid'] ?? '') !== '' && ($config['twilio']['auth_token'] ?? '') !== '',
            default => false,
        };
    }
}
