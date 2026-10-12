<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Employers\SendInterviewInvites;
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
 * Sends one interview invite ("which slot suits you? 1/2/3") on WhatsApp:
 * the first-round template for L1, the next-round template ("you cleared
 * L1 — which slot for L2?") after that.
 * At-most-once: the invite is claimed atomically, so a retry never sends a
 * second message.
 */
final class SendInterviewInviteMessage implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public function __construct(public readonly int $inviteId) {}

    public function handle(WhatsAppClient $whatsapp): void
    {
        $claimed = InterviewInvite::withoutGlobalScopes()->whereKey($this->inviteId)
            ->where('status', InterviewInvite::PENDING)->update(['status' => 'sending']);
        if ($claimed !== 1) {
            return;
        }

        $invite = InterviewInvite::withoutGlobalScopes()->find($this->inviteId);
        $tenant = $invite !== null ? Tenant::query()->find($invite->tenant_id) : null;
        if ($invite === null || $tenant === null) {
            return;
        }

        app(TenantContext::class)->run($tenant, function () use ($invite, $whatsapp): void {
            $job = $invite->job()->with('workspace:id,name')->first();
            $time = SendInterviewInvites::interviewTime($invite);
            $options = array_map(fn (string $date) => SendInterviewInvites::slotLabel($date, $time), $invite->date_options ?? []);
            $company = (string) ($job?->workspace?->name ?? 'A company');
            $role = (string) ($job?->title ?? 'open');
            $slots = [$options[0] ?? '', $options[1] ?? '', $options[2] ?? ''];

            try {
                $wamid = $invite->round === 'l1'
                    ? $whatsapp->sendMessage($invite->phone, '', InterviewTemplates::INVITE, [$invite->firstName(), $company, $role, ...$slots])
                    : $whatsapp->sendMessage($invite->phone, '', InterviewTemplates::NEXT_ROUND, [$invite->firstName(), self::previousRound($invite->round), $role, $company, ...$slots]);
                $invite->update(['status' => InterviewInvite::SENT, 'wamid' => $wamid, 'sent_at' => now(), 'error' => null]);
            } catch (Throwable $e) {
                $invite->update(['status' => InterviewInvite::FAILED, 'error' => Str::limit($e->getMessage(), 240, '')]);
            }
        });
    }

    /** "l2" → "L1": the round they just cleared. */
    private static function previousRound(string $round): string
    {
        return preg_match('/^l(\d+)$/', $round, $m) === 1 && (int) $m[1] > 1 ? 'L'.((int) $m[1] - 1) : 'previous';
    }
}
