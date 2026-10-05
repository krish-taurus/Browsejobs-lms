<?php

declare(strict_types=1);

namespace App\Actions\LiveClasses;

use App\Enums\BatchMemberStatus;
use App\Enums\EnrolmentType;
use App\Models\LiveSession;
use App\Models\User;
use App\Support\Fees\FeeGate;
use Illuminate\Validation\ValidationException;

/**
 * Gates entry to a live class: the student must be an active member of the
 * batch and pass the fee gate. Returns the Zoom join URL only to authorised
 * students so raw links are never handed out otherwise.
 */
final readonly class JoinLiveSession
{
    public function __construct(private FeeGate $feeGate, private RecordAttendance $attendance) {}

    public function handle(LiveSession $session, User $student): string
    {
        $member = $session->batch->members()->where('user_id', $student->id)->first();

        $occupying = array_map(fn (BatchMemberStatus $s) => $s->value, BatchMemberStatus::occupying());

        if ($member === null || ! in_array($member->status->value, $occupying, true)) {
            throw ValidationException::withMessages([
                'enrolment' => 'You are not enrolled in this batch.',
            ]);
        }

        // Self-paced buyers get recordings, not live classes (PRD §6.3).
        if ($member->enrolment_type === EnrolmentType::SelfPaced) {
            throw ValidationException::withMessages([
                'enrolment' => 'Self-paced enrolment does not include live classes. Upgrade to live to join.',
            ]);
        }

        if (! $this->feeGate->allowsLiveAccess($student, $session->batch)) {
            throw ValidationException::withMessages([
                'fees' => 'Access is locked until your fee dues are cleared.',
            ]);
        }

        $opensAt = $session->joinOpensAt();

        if ($opensAt !== null && now()->lessThan($opensAt)) {
            throw ValidationException::withMessages([
                'session' => 'Joining opens at '.$opensAt->timezone(config('app.timezone'))->format('g:i a').'.',
            ]);
        }

        if ($session->zoom_join_url === null) {
            throw ValidationException::withMessages([
                'session' => 'This class is not ready to join yet.',
            ]);
        }

        // Marks attendance the moment the student is actually handed the
        // link, not on a Zoom webhook that mostly never fires for a guest
        // join with no email attached (Sept 2026 fix — see LMS PRD, "class
        // attendance never records"). idempotent: participantJoined() only
        // sets first_joined_at/is_late once, so re-opening the room from
        // this same portal never overwrites the original join time.
        // attended_pct is set outright rather than left for a "leave" event
        // that this flow has no way to observe — same convention the CRM's
        // own manual present/absent mark already uses.
        $attendance = $this->attendance->participantJoined($session, $student, now());
        if ($attendance->attended_pct < 100) {
            $attendance->update(['attended_pct' => 100]);
        }

        return $session->zoom_join_url;
    }
}
