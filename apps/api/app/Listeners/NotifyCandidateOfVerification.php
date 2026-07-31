<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Enums\VerificationStatus;
use App\Events\VerificationSettled;
use App\Models\InAppNotification;
use App\Support\Tenancy\TenantContext;
use App\Support\Verification\VerificationProfile;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Tell the candidate what a reviewer decided (PRD-E F9).
 *
 * A failed check carries the reviewer's reason verbatim — a rejection a
 * candidate cannot act on just produces the same resubmission and another
 * wait. The moment the last required check lands, they are told the badge
 * is live, because that is the state change worth acting on.
 */
final class NotifyCandidateOfVerification implements ShouldQueue
{
    public function handle(VerificationSettled $event): void
    {
        $check = $event->check;
        $candidate = $check->candidate;

        if ($candidate === null) {
            return;
        }

        app(TenantContext::class)->run($candidate->tenant, function () use ($check, $candidate): void {
            $verified = $check->status === VerificationStatus::Verified;
            $badge = $verified && (new VerificationProfile($candidate))->hasBadge();

            InAppNotification::query()->create([
                'tenant_id' => $check->tenant_id,
                'user_id' => $candidate->id,
                'title' => $badge
                    ? 'Your profile is verified'
                    : ($verified
                        ? "{$check->kind->label()} verified"
                        : "{$check->kind->label()} could not be verified"),
                'body' => $badge
                    ? 'Every required check is done. Employers now see a verified stamp on your profile.'
                    : ($verified
                        ? "{$check->kind->label()} is confirmed and now shows on your profile."
                        : ($check->failure_reason ?? 'Open your checks to see what to resubmit.')),
                'url' => '/candidate/verification',
            ]);
        });
    }
}
