<?php

declare(strict_types=1);

namespace App\Support\Otp;

use App\Enums\MessageChannel;
use App\Enums\OtpChannel;
use App\Enums\OtpPurpose;
use App\Models\Scopes\TenantScope;
use App\Models\User;
use App\Support\Crm\PhoneNormalizer;
use App\Support\Messaging\Messenger;
use App\Support\Sms\SmsConfig;
use App\Support\Tenancy\TenantContext;

/**
 * Routes OTP codes through the messaging hub (P2.4). Phone-channel codes go
 * over SMS when a gateway is configured (SMS_PROVIDER), else WhatsApp; email
 * codes over email. Always transactional. Tenant is read from the active
 * context (the OTP endpoint resolves it by host).
 */
final class MessengerOtpNotifier implements OtpNotifier
{
    public function __construct(private readonly Messenger $messenger) {}

    public function send(string $identifier, OtpChannel $channel, string $code, OtpPurpose $purpose): void
    {
        $tenantId = app(TenantContext::class)->id();
        if ($tenantId === null) {
            return;
        }

        $messageChannel = match (true) {
            $channel === OtpChannel::Email => MessageChannel::Email,
            SmsConfig::configured() => MessageChannel::Sms,
            default => MessageChannel::WhatsApp,
        };

        $this->messenger->sendRaw($tenantId, $messageChannel, $identifier, 'otp', ['code' => $code]);

        // WhatsApp drops free-form texts outside the 24h session window until an
        // approved AUTHENTICATION template is live, so mirror the code to the
        // account's email — login must never dead-end on WhatsApp delivery.
        if ($messageChannel === MessageChannel::WhatsApp) {
            $email = $this->emailForPhone($tenantId, $identifier);
            if ($email !== null) {
                $this->messenger->sendRaw($tenantId, MessageChannel::Email, $email, 'otp', ['code' => $code]);
            }
        }
    }

    /** The email on the account matching this phone, if any. */
    private function emailForPhone(int $tenantId, string $identifier): ?string
    {
        $digits = PhoneNormalizer::normalize($identifier);
        if (strlen($digits) < 10) {
            return null;
        }

        // Stored phones vary in formatting (+91 spaces, dashes), so prefilter
        // on the last four digits and confirm on the normalized number.
        $user = User::query()->withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $tenantId)
            ->where('phone', 'like', '%'.substr($digits, -4).'%')
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->get(['phone', 'email'])
            ->first(fn (User $u): bool => str_ends_with(PhoneNormalizer::normalize((string) $u->phone), substr($digits, -10)));

        return $user?->email;
    }
}
