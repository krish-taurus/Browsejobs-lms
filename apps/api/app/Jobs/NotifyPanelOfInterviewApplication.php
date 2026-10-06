<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\MentorSession;
use App\Models\Scopes\TenantScope;
use App\Support\Interviews\InterviewPanel;
use App\Support\Messaging\Messenger;
use App\Support\Tenancy\TenantContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Tells the panel a student has applied for a slot.
 *
 * Everyone who takes that round is messaged, not just the interviewer the
 * student picked: whoever gets to it first can approve or move it, so a single
 * mentor being away never leaves an application sitting unanswered. The web
 * push half of this lands from the CRM side, which is where they work.
 */
class NotifyPanelOfInterviewApplication implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public int $sessionId) {}

    public function handle(Messenger $messenger): void
    {
        $session = MentorSession::query()->withoutGlobalScope(TenantScope::class)
            ->with(['student:id,name,tenant_id', 'mentor.user:id,name'])
            ->find($this->sessionId);

        if ($session === null || $session->interview_round === null) {
            return;
        }

        $tenant = $session->student?->tenant;

        if ($tenant === null) {
            return;
        }

        app(TenantContext::class)->run($tenant, function () use ($session, $messenger): void {
            $round = (int) $session->interview_round;

            $vars = [
                'student' => $session->student?->name ?? 'A student',
                'round' => (string) $round,
                'stage' => InterviewPanel::stageLabel($round),
                'slot' => InterviewPanel::slotLabel($session->starts_at),
                'url' => rtrim((string) config('interviews.crm_url'), '/').'/interviews',
            ];

            foreach (InterviewPanel::forRound($round) as $member) {
                if ($member->user === null) {
                    continue;
                }

                $messenger->send($member->user, 'interview_applied', $vars);
            }
        });
    }
}
