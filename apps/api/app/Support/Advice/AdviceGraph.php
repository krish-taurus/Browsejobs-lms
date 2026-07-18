<?php

declare(strict_types=1);

namespace App\Support\Advice;

use App\Models\JobApplication;
use App\Models\MockInterview;
use App\Models\User;

/**
 * Evidence-backed coaching claims (PRD §6.21). Turns anonymised, tenant-scoped
 * placement aggregates into "students who did X got placed N× as often" — never
 * generic tips. Two privacy invariants are enforced here, and asserted by the
 * aggregation-privacy test:
 *
 *  1. k-anonymity: a claim is suppressed unless BOTH compared groups have at
 *     least MIN_COHORT students, so no individual is re-identifiable.
 *  2. No per-student leakage: claims are aggregate numbers only — never another
 *     student's name, id, or record. Cross-student data stays as counts.
 *
 * Tenant isolation comes from the global scope active in the request context.
 */
final class AdviceGraph
{
    /** Minimum students per compared group before a stat may be shown. */
    public const MIN_COHORT = 5;

    /** Only surface a ratio this strong or stronger (avoids noise). */
    private const MIN_RATIO = 1.2;

    /**
     * Up to a couple of claims relevant to this student. Empty when the cohort is
     * too small to say anything safely.
     *
     * @return list<string>
     */
    public function insightsFor(User $student): array
    {
        $insights = [];

        if ($mock = $this->mockClaim($student)) {
            $insights[] = $mock;
        }

        return $insights;
    }

    /**
     * "Students who practised 3+ mock interviews were placed N× as often." Built
     * from the tenant's applied-at-least-once cohort, split by mock volume.
     */
    private function mockClaim(User $student): ?string
    {
        $cohort = $this->applicantOutcomes(); // [user_id => placed(bool)]
        if ($cohort === []) {
            return null;
        }

        $mockCounts = $this->completedMockCounts(array_keys($cohort));

        $high = ['total' => 0, 'placed' => 0];
        $low = ['total' => 0, 'placed' => 0];
        foreach ($cohort as $userId => $placed) {
            $bucket = ($mockCounts[$userId] ?? 0) >= 3 ? 'high' : 'low';
            if ($bucket === 'high') {
                $high['total']++;
                $high['placed'] += $placed ? 1 : 0;
            } else {
                $low['total']++;
                $low['placed'] += $placed ? 1 : 0;
            }
        }

        // k-anonymity: both groups must clear MIN_COHORT.
        if ($high['total'] < self::MIN_COHORT || $low['total'] < self::MIN_COHORT) {
            return null;
        }

        $rateHigh = $high['placed'] / $high['total'];
        $rateLow = $low['placed'] / $low['total'];
        if ($rateLow <= 0.0) {
            return null; // no baseline to form a ratio
        }

        $ratio = $rateHigh / $rateLow;
        if ($ratio < self::MIN_RATIO) {
            return null;
        }

        $studentMocks = MockInterview::query()
            ->where('user_id', $student->id)
            ->where('status', MockInterview::STATUS_COMPLETED)
            ->count();

        $tail = $studentMocks >= 3
            ? "You're on the strong side of that — keep going."
            : "You've done {$studentMocks} so far.";

        return 'Students who practised 3+ mock interviews were placed '
            .number_format($ratio, 1).'× as often. '.$tail;
    }

    /**
     * The tenant's applied-at-least-once cohort → placed flag. Aggregate use only.
     *
     * @return array<int, bool> user_id => placed
     */
    private function applicantOutcomes(): array
    {
        $outcomes = [];
        JobApplication::query()
            ->select(['user_id', 'status'])
            ->get()
            ->groupBy('user_id')
            ->each(function ($apps, $userId) use (&$outcomes): void {
                $outcomes[(int) $userId] = $apps->contains('status', JobApplication::STATUS_PLACED);
            });

        return $outcomes;
    }

    /**
     * @param  list<int>  $userIds
     * @return array<int, int> user_id => completed mock count
     */
    private function completedMockCounts(array $userIds): array
    {
        return MockInterview::query()
            ->whereIn('user_id', $userIds)
            ->where('status', MockInterview::STATUS_COMPLETED)
            ->selectRaw('user_id, COUNT(*) as c')
            ->groupBy('user_id')
            ->pluck('c', 'user_id')
            ->map(fn ($c) => (int) $c)
            ->all();
    }
}
