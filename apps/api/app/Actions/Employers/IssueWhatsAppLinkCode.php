<?php

declare(strict_types=1);

namespace App\Actions\Employers;

use App\Models\EmployerWhatsappCode;
use App\Models\EmployerWorkspace;
use App\Models\User;
use App\Support\Employers\WhatsAppMessageQr;
use App\Support\Tenancy\TenantContext;

/**
 * The member's current "post jobs on WhatsApp" code — reused while it is
 * valid, so the QR on screen doesn't change on every page load. `fresh`
 * replaces it (e.g. the QR was shared with the wrong person).
 *
 * Each code also gets a WhatsApp-made message QR (wa.me/message/XXXX) so it
 * opens from WhatsApp's own scanner, not just the phone camera. If Meta is
 * unreachable the code still works through the plain wa.me link, and the
 * next page load tries again.
 */
final readonly class IssueWhatsAppLinkCode
{
    public const VALID_DAYS = 30;

    public function __construct(private WhatsAppMessageQr $metaQr) {}

    public function handle(EmployerWorkspace $workspace, User $member, bool $fresh = false): EmployerWhatsappCode
    {
        return app(TenantContext::class)->run($workspace->tenant, function () use ($workspace, $member, $fresh): EmployerWhatsappCode {
            $query = EmployerWhatsappCode::query()
                ->where('employer_workspace_id', $workspace->id)
                ->where('user_id', $member->id);

            if ($fresh) {
                foreach ((clone $query)->whereNotNull('meta_qr_code')->pluck('meta_qr_code') as $old) {
                    $this->metaQr->delete((string) $old);
                }
                (clone $query)->delete();
            } else {
                $current = (clone $query)->where('expires_at', '>', now()->addDay())->latest('id')->first();
                if ($current !== null) {
                    return $this->withMetaQr($current);
                }
            }

            return $this->withMetaQr(EmployerWhatsappCode::create([
                'tenant_id' => $workspace->tenant_id,
                'employer_workspace_id' => $workspace->id,
                'user_id' => $member->id,
                'code' => $this->uniqueCode(),
                'expires_at' => now()->addDays(self::VALID_DAYS),
            ]));
        });
    }

    private function withMetaQr(EmployerWhatsappCode $code): EmployerWhatsappCode
    {
        if ($code->meta_deep_link !== null) {
            return $code;
        }

        $qr = $this->metaQr->create("JOIN {$code->code}");
        if ($qr !== null) {
            $code->update(['meta_qr_code' => $qr['code'], 'meta_deep_link' => $qr['deep_link']]);
        }

        return $code;
    }

    private function uniqueCode(): string
    {
        // No 0/O/1/I — the code is read off a phone screen and sometimes typed.
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        do {
            $code = 'BJ-';
            for ($i = 0; $i < 6; $i++) {
                $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
        } while (EmployerWhatsappCode::withoutGlobalScopes()->where('code', $code)->exists());

        return $code;
    }
}
