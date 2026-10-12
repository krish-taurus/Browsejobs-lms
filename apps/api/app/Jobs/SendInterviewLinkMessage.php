<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\InterviewInvite;
use App\Models\Tenant;
use App\Support\Employers\InterviewTemplates;
use App\Support\Tenancy\TenantContext;
use App\Support\WhatsApp\WhatsAppClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;
use Throwable;

/**
 * "Your <round> interview is ready — reply OK for the link". Sent when a
 * round opens (the booked day for L1; straight away for L2 after L1 clears).
 * Uses the approved template, because the candidate's last message may be
 * more than 24 hours old.
 */
final class SendInterviewLinkMessage implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public function __construct(public readonly int $inviteId) {}

    public function handle(WhatsAppClient $whatsapp): void
    {
        $invite = InterviewInvite::withoutGlobalScopes()->find($this->inviteId);
        $tenant = $invite !== null ? Tenant::query()->find($invite->tenant_id) : null;
        if ($invite === null || $tenant === null || $invite->status !== InterviewInvite::OPEN) {
            return;
        }

        app(TenantContext::class)->run($tenant, function () use ($invite, $whatsapp): void {
            $job = $invite->job()->with('workspace:id,name')->first();

            try {
                $wamid = $whatsapp->sendMessage(
                    $invite->phone,
                    '',
                    InterviewTemplates::LINK,
                    [
                        $invite->firstName(),
                        strtoupper($invite->round),
                        (string) ($job?->title ?? 'open'),
                    ],
                );
                $invite->update(['wamid' => $wamid, 'error' => null]);
            } catch (Throwable $e) {
                $invite->update(['error' => Str::limit('Link not delivered: '.$e->getMessage(), 240, '')]);
            }
        });
    }
}
