<?php

declare(strict_types=1);

namespace App\Support\Candidates;

use App\Enums\VerificationStatus;
use App\Models\CandidateVerificationCheck;
use App\Models\EmployerJobApplication;

/**
 * What share of candidates who reached a given set of stages held a live
 * verified badge. Aggregate only — no ids leave this class, in the same
 * spirit as OutcomeCohort (PRD §6.21).
 */
final class CohortVerificationShare
{
    /**
     * @param  list<string>  $stages
     */
    public function pctAmongStages(array $stages): int
    {
        $candidateIds = EmployerJobApplication::query()
            ->whereIn('stage', $stages)
            ->distinct()
            ->pluck('candidate_id')
            ->all();

        if ($candidateIds === []) {
            return 0;
        }

        /** @var list<string> $required */
        $required = config('verification.badge_requires', []);

        if ($required === []) {
            return 0;
        }

        $verifiedPerCandidate = CandidateVerificationCheck::query()
            ->whereIn('user_id', $candidateIds)
            ->whereIn('kind', $required)
            ->where('status', VerificationStatus::Verified->value)
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->selectRaw('user_id, count(*) as held')
            ->groupBy('user_id')
            ->pluck('held', 'user_id');

        $withBadge = $verifiedPerCandidate
            ->filter(fn ($held): bool => (int) $held === count($required))
            ->count();

        return (int) round(($withBadge / count($candidateIds)) * 100);
    }
}
