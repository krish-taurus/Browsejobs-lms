<?php

declare(strict_types=1);

namespace App\Support\Verification;

use App\Enums\EmployerApplicationStage;
use App\Enums\VerificationKind;
use App\Enums\VerificationStatus;
use App\Models\CandidateVerificationCheck;
use App\Models\EmployerJobApplication;
use App\Models\User;

/**
 * A candidate's verification standing, as both the candidate dashboard and
 * the employer profile view need it (PRD-E F9).
 *
 * The "what completing these is worth" figure is measured, not asserted: it
 * compares the observed shortlist rate of verified against unverified
 * candidates in this tenant, and returns null when there is not enough
 * history to say anything honest. A made-up probability would be a
 * fabricated outcome claim, which the brand rules forbid outright — and it
 * would also be the kind of number a candidate eventually checks.
 */
final readonly class VerificationProfile
{
    /** Below this many settled applications per side, the comparison is noise. */
    private const MIN_SAMPLE_PER_SIDE = 20;

    public function __construct(private User $candidate) {}

    /**
     * @return array{
     *     badge: bool,
     *     verified_count: int,
     *     total_count: int,
     *     checks: list<array{kind: string, label: string, status: string, status_label: string, prompt: string, employer_value: string, required_for_badge: bool, verified_at: string|null, expires_at: string|null, failure_reason: string|null}>,
     *     missing_for_badge: list<string>,
     *     observed_uplift: array{verified_rate: int, unverified_rate: int, sample: int}|null
     * }
     */
    public function summary(): array
    {
        $rows = CandidateVerificationCheck::query()
            ->where('user_id', $this->candidate->id)
            ->get()
            ->keyBy(fn (CandidateVerificationCheck $c): string => $c->kind->value);

        /** @var list<string> $required */
        $required = config('verification.badge_requires', []);

        $checks = [];
        $verifiedCount = 0;
        $missing = [];

        foreach (VerificationKind::cases() as $kind) {
            $row = $rows->get($kind->value);
            $status = $row?->effectiveStatus() ?? VerificationStatus::NotStarted;

            if ($status->counts()) {
                $verifiedCount++;
            } elseif (in_array($kind->value, $required, true)) {
                $missing[] = $kind->label();
            }

            $checks[] = [
                'kind' => $kind->value,
                'label' => $kind->label(),
                'status' => $status->value,
                'status_label' => $status->label(),
                'prompt' => $kind->prompt(),
                'employer_value' => $kind->employerValue(),
                'required_for_badge' => in_array($kind->value, $required, true),
                'verified_at' => $status->counts() ? $row?->verified_at?->toIso8601String() : null,
                'expires_at' => $row?->expires_at?->toIso8601String(),
                'failure_reason' => $status === VerificationStatus::Failed ? $row?->failure_reason : null,
            ];
        }

        return [
            'badge' => $missing === [],
            'verified_count' => $verifiedCount,
            'total_count' => count(VerificationKind::cases()),
            'checks' => $checks,
            'missing_for_badge' => $missing,
            'observed_uplift' => $this->observedUplift(),
        ];
    }

    /** True when every badge-required check is verified and live. */
    public function hasBadge(): bool
    {
        /** @var list<string> $required */
        $required = config('verification.badge_requires', []);

        if ($required === []) {
            return false;
        }

        $verified = CandidateVerificationCheck::query()
            ->where('user_id', $this->candidate->id)
            ->whereIn('kind', $required)
            ->get()
            ->filter(fn (CandidateVerificationCheck $c): bool => $c->effectiveStatus()->counts())
            ->count();

        return $verified === count($required);
    }

    /**
     * Shortlist rate for verified vs unverified candidates across this
     * tenant's employer applications. Null when either side is too thin —
     * saying "we don't have enough data yet" is the honest answer, and the
     * UI is built to render that rather than a placeholder number.
     *
     * @return array{verified_rate: int, unverified_rate: int, sample: int}|null
     */
    private function observedUplift(): ?array
    {
        $verifiedIds = CandidateVerificationCheck::query()
            ->where('status', VerificationStatus::Verified->value)
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->whereIn('kind', config('verification.badge_requires', []))
            ->pluck('user_id')
            ->all();

        $reached = [
            EmployerApplicationStage::Shortlisted->value,
            EmployerApplicationStage::L1->value,
            EmployerApplicationStage::L2->value,
            EmployerApplicationStage::HumanRound->value,
            EmployerApplicationStage::Offer->value,
            EmployerApplicationStage::Hired->value,
        ];

        $rate = function (bool $verified) use ($verifiedIds, $reached): ?array {
            $base = EmployerJobApplication::query()
                ->whereNotNull('graded_at')
                ->when(
                    $verified,
                    fn ($q) => $q->whereIn('candidate_id', $verifiedIds),
                    fn ($q) => $q->whereNotIn('candidate_id', $verifiedIds ?: [0]),
                );

            $total = (clone $base)->count();
            if ($total < self::MIN_SAMPLE_PER_SIDE) {
                return null;
            }

            return ['rate' => (int) round(((clone $base)->whereIn('stage', $reached)->count() / $total) * 100), 'n' => $total];
        };

        $withBadge = $rate(true);
        $without = $rate(false);

        if ($withBadge === null || $without === null) {
            return null;
        }

        return [
            'verified_rate' => $withBadge['rate'],
            'unverified_rate' => $without['rate'],
            'sample' => $withBadge['n'] + $without['n'],
        ];
    }
}
