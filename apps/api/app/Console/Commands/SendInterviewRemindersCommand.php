<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Employers\SendInterviewInvites;
use App\Enums\EmployerInterviewStatus;
use App\Jobs\FinishMockInterviewJob;
use App\Jobs\SendClientUpdate;
use App\Jobs\SendInterviewInviteMessage;
use App\Models\EmployerInterview;
use App\Models\EmployerWhatsappMessage;
use App\Models\InterviewInvite;
use App\Models\MockInterview;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Employers\InterviewTemplates;
use App\Support\Tenancy\TenantContext;
use App\Support\WhatsApp\WhatsAppClient;
use Illuminate\Console\Command;
use Throwable;

/**
 * Every few minutes: WhatsApp candidates about their booked L1/L2 interview.
 *
 *  - About an hour before the slot: a reminder with the link (or the Zoom link).
 *  - Half an hour after the slot, if they have not started: one nudge with the
 *    link and until when it stays open — and the client is told.
 *
 * Also keeps the invites themselves moving: one stuck mid-send (the queue lost
 * it) is sent again, and one nobody answered for a day is offered again once
 * with fresh slots (chased_at), the client being told.
 *
 * Each goes once per invite (reminded_at / nudged_at / chased_at). Inside WhatsApp's
 * 24-hour window (the candidate wrote to us recently) the message carries the
 * link; outside it, the approved "your interview is ready — reply OK"
 * template, and their reply gets the link.
 */
final class SendInterviewRemindersCommand extends Command
{
    protected $signature = 'interviews:remind';

    protected $description = 'Remind candidates of their booked L1/L2 interview, and nudge anyone who has not started it.';

    public function handle(WhatsAppClient $whatsapp, InterviewTemplates $templates): int
    {
        $sent = $this->recoverStuck() + $this->chaseUnanswered();
        $this->finishEndedInterviews();

        $invites = InterviewInvite::withoutGlobalScopes()
            ->whereIn('status', [InterviewInvite::SCHEDULED, InterviewInvite::OPEN])
            ->where('round', '!=', InterviewInvite::ROUND_SCREEN)
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '>', now()->subDays(2))
            ->where(fn ($q) => $q->whereNull('reminded_at')->orWhereNull('nudged_at'))
            ->get();

        foreach ($invites as $invite) {
            $tenant = Tenant::query()->find($invite->tenant_id);
            if ($tenant === null) {
                continue;
            }

            $sent += app(TenantContext::class)->run($tenant, fn (): int => $this->handleInvite($invite, $whatsapp, $templates));
        }

        $this->line("Sent {$sent} interview reminders.");

        return self::SUCCESS;
    }

    /**
     * Safety net for the instant "End interview": an interview the candidate
     * ended whose scorecard job never ran (queue down, worker restart) is queued
     * again after ten minutes. The job is idempotent, so a second run is harmless.
     */
    private function finishEndedInterviews(): void
    {
        MockInterview::query()->withoutGlobalScopes()
            ->where('status', MockInterview::STATUS_IN_PROGRESS)
            ->whereNotNull('ended_at')
            ->where('ended_at', '<', now()->subMinutes(10))
            ->where('ended_at', '>', now()->subDays(2))
            ->pluck('id')
            ->each(fn (int $id) => FinishMockInterviewJob::dispatch($id));
    }

    /** An invite claimed for sending but never finished, or never picked up at all: send it again. */
    private function recoverStuck(): int
    {
        $stuck = InterviewInvite::withoutGlobalScopes()
            ->where(function ($q): void {
                $q->where(fn ($s) => $s->where('status', 'sending')->where('updated_at', '<', now()->subMinutes(15)))
                    ->orWhere(fn ($s) => $s->where('status', InterviewInvite::PENDING)->whereNull('error')
                        ->where('created_at', '<', now()->subMinutes(10))->where('updated_at', '<', now()->subMinutes(10)));
            })
            ->get();

        foreach ($stuck as $i => $invite) {
            $invite->update(['status' => InterviewInvite::PENDING]);
            SendInterviewInviteMessage::dispatch($invite->id)->delay(now()->addSeconds($i * 3));
        }

        return $stuck->count();
    }

    /** Asked a day ago, no reply: ask once more with fresh slots, and tell the client. */
    private function chaseUnanswered(): int
    {
        $due = InterviewInvite::withoutGlobalScopes()
            ->where('status', InterviewInvite::SENT)
            ->where('round', '!=', InterviewInvite::ROUND_SCREEN)
            ->whereNull('chased_at')
            ->where('sent_at', '<', now()->subHours(24))
            ->get();

        foreach ($due as $i => $invite) {
            $invite->update([
                'status' => InterviewInvite::PENDING,
                'date_options' => SendInterviewInvites::dateOptions(now('Asia/Kolkata')),
                'chased_at' => now(),
            ]);
            SendInterviewInviteMessage::dispatch($invite->id)->delay(now()->addSeconds($i * 3));

            $name = (string) (User::withoutGlobalScopes()->whereKey($invite->candidate_user_id)->value('name') ?? 'A candidate');
            SendClientUpdate::dispatch($invite->employer_job_id, "⏳ {$name} has not replied to the {$invite->roundLabel()} invite for a day. I have asked again with fresh slots.");
        }

        return $due->count();
    }

    private function handleInvite(InterviewInvite $invite, WhatsAppClient $whatsapp, InterviewTemplates $templates): int
    {
        $at = $invite->scheduled_at;
        $interview = $invite->employer_interview_id !== null ? EmployerInterview::query()->find($invite->employer_interview_id) : null;
        if ($interview !== null && ($interview->mock_interview_id !== null || in_array($interview->status, [EmployerInterviewStatus::Graded, EmployerInterviewStatus::Submitted], true))) {
            return 0; // Started or done: nothing to remind.
        }
        if ($interview?->expires_at !== null && $interview->expires_at->isPast()) {
            return 0; // The window to take it has closed.
        }

        $job = $invite->job()->with('workspace:id,name')->first();
        $role = (string) ($job?->title ?? 'the');
        $company = (string) ($job?->workspace?->name ?? 'the company');
        $time = $invite->slotLabel();

        // About an hour before.
        if ($invite->reminded_at === null && now()->between($at->copy()->subMinutes(65), $at)) {
            $link = $this->link($invite);
            $body = "⏰ Reminder, {$invite->firstName()}: your {$invite->roundLabel()} AI interview for the {$role} role at {$company} is today, {$time}."
                .($link !== null ? "\n\nStart here: {$link}" : "\n\nYou will get the link here before it starts.")
                ."\n\nIt takes about 15–20 minutes on your phone or laptop. Find a quiet place.";
            $invite->update(['reminded_at' => now()]);

            return $this->send($invite, $body, $whatsapp, $templates) ? 1 : 0;
        }

        // Half an hour after, not started.
        if ($invite->nudged_at === null && $invite->status === InterviewInvite::OPEN && now()->gt($at->copy()->addMinutes(30))) {
            $until = $interview?->expires_at !== null ? ' It stays open until '.$interview->expires_at->copy()->setTimezone('Asia/Kolkata')->format('D j M, g:i A').'.' : '';
            $link = $this->link($invite);
            $body = "{$invite->firstName()}, your {$invite->roundLabel()} interview for the {$role} role ({$time}) has not been started yet.{$until}"
                .($link !== null ? "\n\nStart here: {$link}" : '');
            $invite->update(['nudged_at' => now()]);
            $name = (string) ($invite->candidate?->name ?? 'A candidate');
            if ($job !== null) {
                SendClientUpdate::dispatch($job->id, "⏳ {$name} has not started the {$invite->roundLabel()} interview booked for {$time} yet. We have reminded them.");
            }

            return $this->send($invite, $body, $whatsapp, $templates) ? 1 : 0;
        }

        return 0;
    }

    /** The link to give: the Zoom call, or the interview page once the round is open. */
    private function link(InterviewInvite $invite): ?string
    {
        if ($invite->onZoom()) {
            return (string) $invite->zoom_join_url;
        }

        return $invite->status === InterviewInvite::OPEN ? $invite->link() : null;
    }

    private function send(InterviewInvite $invite, string $body, WhatsAppClient $whatsapp, InterviewTemplates $templates): bool
    {
        $windowOpen = EmployerWhatsappMessage::query()
            ->where('phone', $invite->phone)
            ->where('direction', 'in')
            ->where('created_at', '>', now()->subHours(23))
            ->exists();

        $message = EmployerWhatsappMessage::query()->create([
            'tenant_id' => $invite->tenant_id,
            'interview_invite_id' => $invite->id,
            'phone' => $invite->phone,
            'direction' => 'out',
            'body' => $body,
            'status' => 'sent',
        ]);

        try {
            if ($windowOpen) {
                $wamid = $whatsapp->sendMessage($invite->phone, $body);
            } elseif ($templates->approved(InterviewTemplates::LINK) && ($invite->status === InterviewInvite::OPEN || $invite->onZoom())) {
                // "Your <round> interview for <role> is ready — reply OK and we will send your link."
                $wamid = $whatsapp->sendMessage($invite->phone, '', InterviewTemplates::LINK, [$invite->firstName(), $invite->roundLabel(), (string) ($invite->job?->title ?? 'open')]);
            } else {
                $message->update(['status' => 'failed']);

                return false;
            }
            $message->update(['wamid' => $wamid]);

            return true;
        } catch (Throwable $e) {
            report($e);
            $message->update(['status' => 'failed']);

            return false;
        }
    }
}
