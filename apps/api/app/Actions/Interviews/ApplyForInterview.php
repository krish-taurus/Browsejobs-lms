<?php

declare(strict_types=1);

namespace App\Actions\Interviews;

use App\Models\MentorProfile;
use App\Models\MentorSession;
use App\Models\User;
use App\Support\Interviews\InterviewPanel;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * A student applies for an interview slot.
 *
 * Unlike a mentoring booking this does NOT confirm anything: it records a
 * request that a Tech Mentor (round 1) or Tech Manager (round 2) then approves
 * or moves from the CRM. It also costs no mentor credits — the interview is
 * part of the placement journey, not a purchased 1:1.
 *
 * Every rule that decides whether an application is allowed lives here, so the
 * API, the tests and any future admin path cannot drift apart.
 */
final readonly class ApplyForInterview
{
    public function handle(User $student, int $round, int $mentorProfileId, CarbonImmutable $startsAt): MentorSession
    {
        if (! in_array($round, [MentorProfile::ROUND_SCREENING, MentorProfile::ROUND_FINAL], true)) {
            throw ValidationException::withMessages(['round' => 'There are only two interview rounds.']);
        }

        // The whole point of two rounds: the final round is earned, not chosen.
        if ($round === MentorProfile::ROUND_FINAL && ! MentorSession::hasClearedRoundOne($student->id)) {
            throw ValidationException::withMessages([
                'round' => 'Clear your Round 1 interview first — the final round opens once a Tech Mentor passes you.',
            ]);
        }

        if ($round === MentorProfile::ROUND_SCREENING && MentorSession::hasClearedRoundOne($student->id)) {
            throw ValidationException::withMessages([
                'round' => 'You have already cleared Round 1. Apply for the final round instead.',
            ]);
        }

        // One live application per round. Without this a student can spam the
        // panel with a dozen slots and the CRM queue becomes unusable.
        //
        // "Live" has to mean still ahead of them. A booking whose slot passed
        // and that nobody ever marked up used to block the round forever: the
        // student could neither attend it nor replace it.
        $lapseCutoff = CarbonImmutable::now()
            ->subHours((int) config('interviews.lapse_after_hours', 24));

        $live = MentorSession::query()
            ->where('student_id', $student->id)
            ->where('interview_round', $round)
            ->whereIn('approval_status', [MentorSession::APPROVAL_PENDING, MentorSession::APPROVAL_APPROVED])
            ->whereNull('outcome')
            ->whereRaw('DATE_ADD(starts_at, INTERVAL COALESCE(duration_minutes, 45) MINUTE) > ?', [$lapseCutoff])
            ->exists();

        if ($live) {
            throw ValidationException::withMessages([
                'round' => 'You already have a Round '.$round.' interview in progress.',
            ]);
        }

        $notice = (int) config('interviews.min_notice_hours', 12);
        if ($startsAt->lt(CarbonImmutable::now()->addHours($notice))) {
            throw ValidationException::withMessages([
                'starts_at' => 'Pick a slot at least '.$notice.' hours from now, so the panel has time to confirm it.',
            ]);
        }

        $window = (int) config('interviews.booking_window_days', 21);
        if ($startsAt->gt(CarbonImmutable::now()->addDays($window))) {
            throw ValidationException::withMessages([
                'starts_at' => 'Slots open '.$window.' days ahead. Pick a nearer date.',
            ]);
        }

        $mentor = InterviewPanel::forRound($round)->firstWhere('id', $mentorProfileId);

        if ($mentor === null) {
            throw ValidationException::withMessages([
                'mentor_profile_id' => 'That interviewer does not take Round '.$round.'.',
            ]);
        }

        return DB::transaction(function () use ($student, $mentor, $round, $startsAt): MentorSession {
            // Two students racing for the same slot: the second one loses, and
            // an already-approved booking beats a pending one for the same time.
            $taken = MentorSession::query()
                ->where('mentor_profile_id', $mentor->id)
                ->where('starts_at', $startsAt)
                ->where('status', MentorSession::STATUS_BOOKED)
                ->whereIn('approval_status', [MentorSession::APPROVAL_PENDING, MentorSession::APPROVAL_APPROVED])
                ->lockForUpdate()
                ->exists();

            if ($taken) {
                throw ValidationException::withMessages(['starts_at' => 'Someone just asked for that slot. Pick another.']);
            }

            return MentorSession::query()->create([
                'tenant_id' => $student->tenant_id,
                'mentor_profile_id' => $mentor->id,
                'student_id' => $student->id,
                'purpose' => MentorSession::PURPOSE_INTERVIEW,
                'status' => MentorSession::STATUS_BOOKED,
                'interview_round' => $round,
                'approval_status' => MentorSession::APPROVAL_PENDING,
                'starts_at' => $startsAt,
                'requested_starts_at' => $startsAt,
                'duration_minutes' => (int) config('interviews.duration_minutes', 45),
            ]);
        });
    }
}
