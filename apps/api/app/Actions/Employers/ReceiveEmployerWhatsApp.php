<?php

declare(strict_types=1);

namespace App\Actions\Employers;

use App\Jobs\ProcessEmployerWhatsAppMessage;
use App\Models\EmployerWhatsappCode;
use App\Models\EmployerWhatsappLink;
use App\Models\EmployerWhatsappMessage;
use App\Models\Tenant;

/**
 * Front door for a WhatsApp message the CRM received on the shared business
 * number. Decides — fast, no AI — whether it belongs to the hiring chat:
 * either the sender's phone is linked to an employer workspace, or the text
 * carries a JOIN code. If so the message is stored once (Meta's retries are
 * dropped by wamid) and the agent's reply is queued; otherwise the CRM keeps
 * handling it as before.
 */
final readonly class ReceiveEmployerWhatsApp
{
    public const NOT_EMPLOYER = 'not_employer';

    public const QUEUED = 'queued';

    public const DUPLICATE = 'duplicate';

    public const CODE_PATTERN = '/\bBJ-[A-HJ-NP-Z2-9]{6}\b/i';

    public function handle(string $from, string $text, ?string $wamid = null, ?string $profileName = null): string
    {
        $phone = preg_replace('/\D+/', '', $from) ?? '';
        if (strlen($phone) === 10) {
            $phone = '91'.$phone;
        }
        if (strlen($phone) < 11 || trim($text) === '') {
            return self::NOT_EMPLOYER;
        }

        if ($wamid !== null && $wamid !== '' && EmployerWhatsappMessage::withoutGlobalScopes()->where('wamid', $wamid)->exists()) {
            return self::DUPLICATE;
        }

        $link = EmployerWhatsappLink::withoutGlobalScopes()->where('phone', $phone)->first();
        $code = preg_match(self::CODE_PATTERN, $text, $m) === 1
            ? EmployerWhatsappCode::withoutGlobalScopes()->where('code', strtoupper($m[0]))->first()
            : null;
        $hasCodeText = isset($m[0]);

        if ($link === null && ! $hasCodeText) {
            return self::NOT_EMPLOYER;
        }

        $tenantId = $link->tenant_id ?? $code->tenant_id ?? $this->defaultTenantId();
        if ($tenantId === null) {
            return self::NOT_EMPLOYER;
        }

        $message = EmployerWhatsappMessage::withoutGlobalScopes()->create([
            'tenant_id' => $tenantId,
            'employer_whatsapp_link_id' => $link?->id,
            'phone' => $phone,
            'direction' => 'in',
            'body' => mb_substr($text, 0, 4000),
            'wamid' => $wamid !== '' ? $wamid : null,
            'status' => 'received',
        ]);

        if ($link !== null && $profileName !== null && $profileName !== '' && $link->profile_name === null) {
            $link->forceFill(['profile_name' => mb_substr($profileName, 0, 255)])->save();
        }

        ProcessEmployerWhatsAppMessage::dispatch($message->id);

        return self::QUEUED;
    }

    private function defaultTenantId(): ?int
    {
        $slug = (string) (config('services.whatsapp.tenant_slug') ?: config('tenancy.default_slug'));
        if ($slug === '') {
            return null;
        }

        $id = Tenant::query()->where('slug', $slug)->value('id');

        return $id !== null ? (int) $id : null;
    }
}
