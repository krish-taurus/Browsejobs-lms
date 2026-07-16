<?php

declare(strict_types=1);

namespace App\Support\Otp;

use App\Enums\MessageChannel;
use App\Enums\OtpChannel;
use App\Enums\OtpPurpose;
use App\Support\Messaging\Messenger;
use App\Support\Tenancy\TenantContext;

/**
 * Routes OTP codes through the messaging hub (P2.4). SMS-channel codes go over
 * WhatsApp (primary); email codes over email. Always transactional. Tenant is
 * read from the active context (the OTP endpoint resolves it by host).
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

        $messageChannel = $channel === OtpChannel::Email ? MessageChannel::Email : MessageChannel::WhatsApp;

        $this->messenger->sendRaw($tenantId, $messageChannel, $identifier, 'otp', ['code' => $code]);
    }
}
