<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Enums\EntitlementFeature;
use App\Models\CreditTransaction;
use App\Models\User;
use App\Support\Entitlements\EntitlementService;
use App\Support\Tenancy\TenantContext;

/**
 * The free tier a new account starts on (PRD-E F12).
 *
 * Anyone can register — you do not have to be enrolled in a BrowseJobs
 * programme to be a job seeker here. Until now that meant an open signup
 * received nothing: included quota is only granted on purchase, so a job
 * seeker who arrived from the public board could apply but never sit the
 * mock the application depends on. That is a dead end, not a funnel.
 *
 * A handful of free mocks and CV generations makes the product usable
 * immediately and gives the upgrade prompt something honest to stand on:
 * the candidate has already seen what they are buying more of.
 *
 * Idempotent by reason string — re-running never double-grants.
 */
final readonly class GrantSignupCredits
{
    private const REASON = 'signup:free_tier';

    public function __construct(private EntitlementService $entitlements) {}

    public function handle(User $user): void
    {
        app(TenantContext::class)->run($user->tenant, function () use ($user): void {
            $alreadyGranted = CreditTransaction::query()
                ->where('user_id', $user->id)
                ->where('reason', self::REASON)
                ->exists();

            if ($alreadyGranted) {
                return;
            }

            $mocks = (int) config('monetization.signup.free_mocks', 2);
            $cvs = (int) config('monetization.signup.free_cvs', 1);

            if ($mocks > 0) {
                $this->entitlements->grantCredits(
                    $user,
                    EntitlementFeature::VoiceMock->value,
                    $mocks,
                    self::REASON,
                );
            }

            if ($cvs > 0) {
                $this->entitlements->grantCredits(
                    $user,
                    EntitlementFeature::Cv->value,
                    $cvs,
                    self::REASON,
                );
            }
        });
    }
}
