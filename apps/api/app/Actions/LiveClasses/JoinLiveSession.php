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

        // The door opens shortly before start and closes when the class ends —
        // after that, the recording is the way in.
        $openMinutes = (int) config('live_classes.join_open_minutes', 10);
        $start = $session->scheduled_start;
        $end = $session->scheduled_end ?? $start?->copy()->addMinutes(90);

        if ($start !== null && now()->lt($start->copy()->subMinutes($openMinutes))) {
            throw ValidationException::withMessages([
                'session' => "Join opens {$openMinutes} minutes before the class starts (".$start->timezone(config('app.timezone'))->format('D, d M · h:i A').').',
            ]);
        }

        if ($end !== null && now()->gt($end)) {
            throw ValidationException::withMessages([
                'session' => 'This class has ended — the recording will appear on your Recordings page shortly.',
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
