<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\Commands\Concerns\ResolvesCrmTargets;
use App\Models\MentorSession;
use App\Models\Scopes\TenantScope;
use App\Support\Interviews\InterviewPanel;
use App\Support\Messaging\Messenger;
use App\Support\Zoom\ZoomClient;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Throwable;

/**
 * The panel acting on an interview application — driven from the CRM, which is
 * where Tech Mentors and Tech Managers actually work.
 *
 *   interviews:review 12 approve
 *   interviews:review 12 reschedule --at="2026-08-14 15:00" --note="Clashes with a client call"
 *   interviews:review 12 outcome --result=cleared --note="Strong on SQL"
 *   interviews:review 12 decline --note="Please finish module 3 first"
 *
 * Approve and reschedule both WhatsApp the student, because both change when
 * they need to show up. `outcome` is what opens round 2 (or closes the door),
 * so it is deliberately a separate, explicit step rather than something
 * inferred from the session having finished.
 */
class ReviewInterview extends Command
{
    use ResolvesCrmTargets;

    protected $signature = 'interviews:review
        {session : mentor_sessions.id}
        {action : approve|reschedule|outcome|decline}
        {--at= : New slot, for reschedule (Y-m-d H:i, IST)}
        {--result= : cleared|not_cleared, for outcome}
        {--note= : Shown to the student}
        {--by= : CRM user name, recorded on the review}';

    protected $description = 'Approve, reschedule, decline or record the outcome of an interview application.';

    public function handle(Messenger $messenger): int
    {
        $session = MentorSession::query()->withoutGlobalScope(TenantScope::class)
            // phone and email must be selected: Messenger reads them off the
            // model to address the message, and a trimmed select silently
            // produces a message with no recipient.
            ->with(['student:id,name,tenant_id,phone,email', 'mentor.user:id,name'])
            ->find((int) $this->argument('session'));

        if ($session === null || $session->interview_round === null) {
            $this->error('Interview application not found.');

            return self::FAILURE;
        }

        $tenant = $session->student?->tenant;

        if ($tenant === null) {
            $this->error('That application has no tenant.');

            return self::FAILURE;
        }

        $note = (string) ($this->option('note') ?? '');
        $action = (string) $this->argument('action');

        return $this->runForTenant($tenant->id, function () use ($session, $action, $note, $messenger): int {
            return match ($action) {
                'approve' => $this->approve($session, $note, $messenger),
                'reschedule' => $this->reschedule($session, $note, $messenger),
                'outcome' => $this->outcome($session, $note, $messenger),
                'decline' => $this->decline($session, $note, $messenger),
                default => $this->unknown($action),
            };
        });
    }

    private function approve(MentorSession $session, string $note, Messenger $messenger): int
    {
        $this->ensureMeeting($session);

        $session->forceFill([
            'approval_status' => MentorSession::APPROVAL_APPROVED,
            'review_note' => $note !== '' ? $note : null,
            'reviewed_at' => now(),
        ])->save();

        $this->tellStudent($session, 'interview_approved', $messenger);
        $this->info("Approved: {$session->student?->name} — ".InterviewPanel::shortLabel((int) $session->interview_round));

        return self::SUCCESS;
    }

    private function reschedule(MentorSession $session, string $note, Messenger $messenger): int
    {
        $at = (string) ($this->option('at') ?? '');

        if ($at === '') {
            $this->error('Rescheduling needs --at.');

            return self::FAILURE;
        }

        // Times come from the CRM in IST — the only clock this platform uses.
        $starts = CarbonImmutable::parse($at, 'Asia/Kolkata');

        if ($starts->isPast()) {
            $this->error('That slot is in the past.');

            return self::FAILURE;
        }

        $was = $session->starts_at;

        $session->forceFill([
            'starts_at' => $starts,
            // Moving a slot also settles it: the student is not left waiting on
            // a second confirmation for a time the panel just chose.
            'approval_status' => MentorSession::APPROVAL_APPROVED,
            'review_note' => $note !== '' ? $note : null,
            'reviewed_at' => now(),
            // A moved slot is a fresh commitment; let the reminders fire again.
            'reminded_24h_at' => null,
            'reminded_1h_at' => null,
        ])->save();

        // The room must follow the slot, or everyone turns up to a meeting that
        // Zoom still thinks starts at the old time.
        $this->ensureMeeting($session->refresh());

        $this->tellStudent($session, 'interview_rescheduled', $messenger, [
            'was' => InterviewPanel::slotLabel($was),
        ]);

        $this->info("Moved to {$starts->format('d M Y H:i')} IST for {$session->student?->name}.");

        return self::SUCCESS;
    }

    private function outcome(MentorSession $session, string $note, Messenger $messenger): int
    {
        $result = (string) ($this->option('result') ?? '');

        if (! in_array($result, [MentorSession::OUTCOME_CLEARED, MentorSession::OUTCOME_NOT_CLEARED], true)) {
            $this->error('--result must be cleared or not_cleared.');

            return self::FAILURE;
        }

        $session->forceFill([
            'outcome' => $result,
            'status' => MentorSession::STATUS_COMPLETED,
            'review_note' => $note !== '' ? $note : $session->review_note,
            'reviewed_at' => now(),
        ])->save();

        $opens = $result === MentorSession::OUTCOME_CLEARED && (int) $session->interview_round === 1;

        // Sitting an interview and hearing nothing back is the worst part of
        // any hiring process. Both verdicts get a message, and a cleared round 1
        // says what to do next rather than leaving them to guess.
        $this->tellStudent(
            $session,
            $result === MentorSession::OUTCOME_CLEARED ? 'interview_cleared' : 'interview_not_cleared',
            $messenger,
            ['next' => $opens
                ? 'Your final round with the Tech Manager is now open — book a slot here:'
                : 'See your interview page:'],
        );

        $this->info("Recorded {$result} for {$session->student?->name}.".($opens ? ' Final round is now open to them.' : ''));

        return self::SUCCESS;
    }

    private function decline(MentorSession $session, string $note, Messenger $messenger): int
    {
        $session->forceFill([
            'approval_status' => MentorSession::APPROVAL_DECLINED,
            'status' => MentorSession::STATUS_CANCELLED,
            'review_note' => $note !== '' ? $note : null,
            'reviewed_at' => now(),
            'cancelled_at' => now(),
        ])->save();

        $this->tellStudent($session, 'interview_declined', $messenger);

        $this->info("Declined {$session->student?->name}'s application.");

        return self::SUCCESS;
    }

    private function unknown(string $action): int
    {
        $this->error("Unknown action '{$action}'. Use approve, reschedule, outcome or decline.");

        return self::FAILURE;
    }

    /**
     * Give the interview a Zoom room — creating it on approval, and moving it
     * when the slot moves.
     *
     * Cloud recording is switched on here, which is what later fills the
     * recording column for both the panel and the student. Ordinary mentoring
     * 1:1s stay direct-connect (ADR 0043); an interview is different — it is a
     * scheduled, recorded assessment.
     *
     * Deliberately best-effort: if Zoom is down, the approval still stands and
     * the student still gets told their slot is confirmed. Losing the room is a
     * problem you can fix in a minute; losing the approval is not.
     */
    private function ensureMeeting(MentorSession $session): void
    {
        $zoom = app(ZoomClient::class);

        try {
            if ($session->zoom_meeting_id !== null) {
                $zoom->updateMeeting(
                    (string) $session->zoom_meeting_id,
                    $session->starts_at,
                    (int) $session->duration_minutes,
                );

                return;
            }

            $round = (int) $session->interview_round;

            $meeting = $zoom->createMeeting(
                InterviewPanel::shortLabel($round).' — '.($session->student?->name ?? 'Interview'),
                $session->starts_at,
                (int) $session->duration_minutes,
                null,
                true,
            );

            $session->forceFill([
                'zoom_meeting_id' => $meeting->id,
                'join_url' => $meeting->joinUrl,
                'start_url' => $meeting->startUrl,
            ])->save();
        } catch (Throwable $e) {
            report($e);
            $this->warn('Zoom room could not be set up ('.$e->getMessage().'). The slot is still confirmed — retry from the CRM.');
        }
    }

    /**
     * @param  array<string, string>  $extra
     */
    private function tellStudent(MentorSession $session, string $key, Messenger $messenger, array $extra = []): void
    {
        if ($session->student === null) {
            return;
        }

        $round = (int) $session->interview_round;

        $messenger->send($session->student, $key, array_merge([
            'round' => (string) $round,
            'stage' => InterviewPanel::stageLabel($round),
            'slot' => InterviewPanel::slotLabel($session->starts_at),
            'interviewer' => $session->mentor?->user?->name ?? 'your interviewer',
            'note' => (string) ($session->review_note ?? ''),
            'url' => (string) config('interviews.student_url'),
        ], $extra));
    }
}
