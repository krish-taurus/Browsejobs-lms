<?php

declare(strict_types=1);

namespace App\Support\Verification\Providers;

use App\Enums\VerificationKind;
use App\Models\User;
use App\Support\Verification\VerificationOutcome;
use App\Support\Verification\VerificationProvider;

/**
 * The provider that ships (ADR 0052): a submission goes to a human on the
 * ops team, who verifies or rejects it from the admin queue.
 *
 * It supports every kind, which is what makes it a safe default — the badge,
 * the employer stamp and the candidate checklist all work end to end today,
 * and pointing a kind at DigiLocker or EPFO later is a config change plus
 * one class. It never returns `verified` on its own: a human does that.
 */
final class ManualReviewProvider implements VerificationProvider
{
    public function name(): string
    {
        return 'manual';
    }

    public function supports(VerificationKind $kind): bool
    {
        return true;
    }

    /**
     * @param  array<string, mixed>  $submission
     */
    public function start(User $candidate, VerificationKind $kind, array $submission): VerificationOutcome
    {
        return VerificationOutcome::pending();
    }
}
