<?php

declare(strict_types=1);

namespace App\Actions\Mentoring;

use App\Enums\EntitlementFeature;
use App\Models\MentorSession;
use App\Support\Entitlements\EntitlementService;
use Illuminate\Validation\ValidationException;

/**
 * No-show tracking (PRD §6.11). A student no-show keeps the credit spent
 * (the slot was held and lost); a mentor no-show refunds it. Either way the
 * record is permanent — no-show rates surface on both sides.
 */
final readonly class MarkMentorNoShow
{
    public function __construct(private EntitlementService $entitlements) {}

    public function handle(MentorSession $session, string $side): MentorSession
    {
        if ($session->status !== MentorSession::STATUS_BOOKED) {
            throw ValidationException::withMessages(['session' => 'This session is no longer active.']);
        }

        if ($session->starts_at->isFuture()) {
            throw ValidationException::withMessages(['session' => 'A no-show can only be marked after the start time.']);
        }

        if (! in_array($side, ['student', 'mentor'], true)) {
            throw ValidationException::withMessages(['side' => 'Side must be student or mentor.']);
        }

        $session->update([
            'status' => MentorSession::STATUS_NO_SHOW,
            'no_show_side' => $side,
        ]);

        if ($side === 'mentor' && $session->student !== null) {
            $this->entitlements->grantCredits(
                $session->student,
                EntitlementFeature::Mentor->value,
                1,
                "mentor_refund:no_show:{$session->id}",
            );
        }

        return $session->refresh();
    }
}
