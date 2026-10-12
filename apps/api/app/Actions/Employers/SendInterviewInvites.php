<?php

declare(strict_types=1);

namespace App\Actions\Employers;

use App\Enums\EmployerApplicationStage;
use App\Jobs\SendInterviewInviteMessage;
use App\Models\CvDocument;
use App\Models\EmployerJob;
use App\Models\EmployerJobApplication;
use App\Models\InterviewInvite;
use App\Models\JobShortlistEntry;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use App\Support\Employers\InterviewTemplates;
use App\Support\Employers\ShortlistSettings;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * The message agent (hiring step 5): everyone on a JD's AI shortlist gets a
 * WhatsApp asking which of the next three working days suits them for the
 * first interview round. Their reply books it (CandidateChatAgent); on that
 * day the round opens and they get the link.
 *
 * Per person: make sure they have an account (client/Naukri candidates get
 * a light one), put them on the pipeline at Shortlisted, and queue one
 * invite. Idempotent — someone already invited is skipped, never messaged
 * twice. Only runs when the invite template is approved by Meta.
 *
 * Called from the "Send interview invites" button, and by the shortlist agent
 * itself (no actor) when the JD's auto_invite setting is on.
 */
final readonly class SendInterviewInvites
{
    public function __construct(
        private EnsureCandidateAccount $accounts,
        private InterviewTemplates $templates,
        private AuditLogger $audit,
    ) {}

    /**
     * @param  list<int>|null  $entryIds  null = everyone on the shortlist
     * @return array{queued: int, skipped: array<string, int>}
     */
    public function handle(EmployerJob $job, ?User $actor, ?array $entryIds = null): array
    {
        if (! $this->templates->approved(InterviewTemplates::INVITE)) {
            throw ValidationException::withMessages([
                'invite' => 'The WhatsApp interview-invite message is not approved by Meta yet. Submit it below and try again once it shows Approved.',
            ]);
        }

        return app(TenantContext::class)->run($job->tenant, function () use ($job, $actor, $entryIds): array {
            $entries = JobShortlistEntry::query()
                ->where('employer_job_id', $job->id)
                ->where('status', JobShortlistEntry::STATUS_SHORTLISTED)
                ->when($entryIds !== null, fn ($q) => $q->whereIn('id', $entryIds))
                ->with(['employerCandidate', 'candidateUser'])
                ->orderBy('rank')
                ->get();

            $queued = 0;
            $skipped = [];
            $skip = function (string $why) use (&$skipped): void {
                $skipped[$why] = ($skipped[$why] ?? 0) + 1;
            };
            $dates = self::dateOptions(now('Asia/Kolkata'));

            foreach ($entries as $i => $entry) {
                $user = $entry->candidateUser
                    ?? ($entry->employerCandidate !== null ? $this->accounts->handle($entry->employerCandidate) : null);
                if ($user === null) {
                    $skip('no mobile number');

                    continue;
                }

                $phone = self::whatsappNumber($user->phone);
                if ($phone === null) {
                    $skip('no mobile number');

                    continue;
                }

                $application = $this->onPipeline($job, $user);
                if ($application->stage->isTerminal()) {
                    $skip('already rejected or withdrawn');

                    continue;
                }

                $invite = InterviewInvite::query()->firstOrNew([
                    'employer_job_application_id' => $application->id,
                    'round' => 'l1',
                ]);
                if ($invite->exists && $invite->status !== InterviewInvite::FAILED) {
                    $skip('already invited');

                    continue;
                }

                $invite->fill([
                    'tenant_id' => $job->tenant_id,
                    'employer_job_id' => $job->id,
                    'candidate_user_id' => $user->id,
                    'phone' => $phone,
                    'status' => InterviewInvite::PENDING,
                    'date_options' => $dates,
                    'chosen_date' => null,
                    'token' => $invite->token ?? Str::random(40),
                    'error' => null,
                    'sent_by_user_id' => $actor?->id,
                ])->save();

                // A steady pace, not a burst — WhatsApp watches sending quality.
                SendInterviewInviteMessage::dispatch($invite->id)->delay(now()->addSeconds($queued * 3));
                $queued++;
            }

            $this->audit->log('employer.interview_invites_sent', $job, [
                'queued' => $queued,
                'skipped' => $skipped,
            ], $actor);

            return ['queued' => $queued, 'skipped' => $skipped];
        });
    }

    /**
     * The next three working days (Mon–Sat), starting tomorrow, as Y-m-d.
     *
     * @return list<string>
     */
    public static function dateOptions(Carbon $from): array
    {
        $out = [];
        $day = $from->copy()->startOfDay();
        while (count($out) < 3) {
            $day->addDay();
            if (! $day->isSunday()) {
                $out[] = $day->toDateString();
            }
        }

        return $out;
    }

    /** Digits with the country code (10-digit Indian numbers get 91), or null when unusable. */
    public static function whatsappNumber(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone) ?? '';
        if (strlen($digits) === 10) {
            $digits = '91'.$digits;
        }

        return strlen($digits) < 11 ? null : $digits;
    }

    /** The JD's interview time ("11:00", India) for this invite's slots. */
    public static function interviewTime(InterviewInvite $invite): string
    {
        $job = EmployerJob::withoutGlobalScopes()->find($invite->employer_job_id);

        return $job !== null ? ShortlistSettings::forJob($job)->interviewTime : ShortlistSettings::DEFAULT_INTERVIEW_TIME;
    }

    /** A slot's start: the day at the interview time, India time. */
    public static function slotStart(string $date, string $time): Carbon
    {
        return Carbon::parse("{$date} {$time}", 'Asia/Kolkata');
    }

    /** Minutes between two Zoom interviews on the same day (one Zoom licence runs one meeting at a time). */
    public const ZOOM_SLOT_MINUTES = 30;

    /**
     * The first free half-hour from the interview time on that day: every Zoom
     * interview runs under the same Zoom host, which can hold only one live
     * meeting at once, so two candidates are never booked at the same time.
     */
    public static function freeZoomSlot(string $date, string $time, ?int $exceptInviteId = null): Carbon
    {
        $start = self::slotStart($date, $time);
        $taken = InterviewInvite::withoutGlobalScopes()
            ->whereIn('status', [InterviewInvite::SCHEDULED, InterviewInvite::OPEN])
            ->whereNotNull('zoom_join_url')
            ->when($exceptInviteId !== null, fn ($q) => $q->whereKeyNot($exceptInviteId))
            ->whereBetween('scheduled_at', [$start->copy()->startOfDay()->utc(), $start->copy()->endOfDay()->utc()])
            ->pluck('scheduled_at')
            ->map(fn ($at) => Carbon::parse($at)->getTimestamp())
            ->all();

        $slot = $start->copy();
        while (collect($taken)->contains(fn (int $at) => abs($at - $slot->getTimestamp()) < self::ZOOM_SLOT_MINUTES * 60)) {
            $slot->addMinutes(self::ZOOM_SLOT_MINUTES);
        }

        return $slot;
    }

    /** "Tue 14 Oct, 11:00 AM" */
    public static function slotLabel(string $date, string $time): string
    {
        return self::slotStart($date, $time)->format('D j M, g:i A');
    }

    public static function label(string $date): string
    {
        return Carbon::parse($date, 'Asia/Kolkata')->format('D j M');
    }

    /** Same as ShortlistTalentPoolCandidate: a light application at Shortlisted, only ever moved forward. */
    private function onPipeline(EmployerJob $job, User $user): EmployerJobApplication
    {
        $application = EmployerJobApplication::query()
            ->where('employer_job_id', $job->id)
            ->where('candidate_id', $user->id)
            ->first();

        if ($application === null) {
            return EmployerJobApplication::query()->create([
                'tenant_id' => $job->tenant_id,
                'employer_job_id' => $job->id,
                'candidate_id' => $user->id,
                'cv_document_id' => CvDocument::query()->where('user_id', $user->id)->latest()->value('id'),
                'mock_attempts' => 0,
                'stage' => EmployerApplicationStage::Shortlisted->value,
            ]);
        }

        if ($application->stage->canAdvanceTo(EmployerApplicationStage::Shortlisted)) {
            $application->update(['stage' => EmployerApplicationStage::Shortlisted->value]);
        }

        return $application->fresh();
    }
}
