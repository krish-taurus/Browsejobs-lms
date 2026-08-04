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
    public function __construct(private FeeGate $feeGate) {}

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

        return $session->zoom_join_url;
    }
}
