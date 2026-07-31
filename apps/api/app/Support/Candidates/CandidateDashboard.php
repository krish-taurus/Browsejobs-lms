<?php

declare(strict_types=1);

namespace App\Support\Candidates;

use App\Enums\EmployerApplicationStage;
use App\Enums\EntitlementFeature;
use App\Models\CvProfile;
use App\Models\EmployerJobApplication;
use App\Models\MockInterview;
use App\Models\User;
use App\Support\Entitlements\EntitlementService;
use App\Support\Verification\VerificationProfile;
use Illuminate\Support\Collection;

/**
 * Everything the candidate dashboard renders, in one round trip (PRD-E F11).
 *
 * The honesty rules that shape this class, because they are easy to erode
 * later: every comparative number is measured from this tenant's own
 * applications and is returned as null when the sample is too small to mean
 * anything. Nothing here asserts that an action causes an outcome — the
 * cohort panel reports what separated two groups, which is a correlation,
 * and the UI is required to label it as one. There is no path in this class
 * that produces a probability of being hired.
 */
final readonly class CandidateDashboard
{
    /** Under this many settled applications, cohort medians are noise. */
    private const MIN_COHORT = 12;

    public function __construct(
        private User $candidate,
        private EntitlementService $entitlements,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(): array
    {
        $applications = EmployerJobApplication::query()
            ->where('candidate_id', $this->candidate->id)
            ->with(['job.workspace'])
            ->latest()
            ->get();

        $cohort = $this->cohort();
        $mockHistory = $this->mockHistory();
        $live = $applications
            ->reject(fn (EmployerJobApplication $a): bool => in_array(
                $a->stage instanceof EmployerApplicationStage ? $a->stage->value : (string) $a->stage,
                [EmployerApplicationStage::Rejected->value, EmployerApplicationStage::Withdrawn->value],
                true,
            ))
            ->count();

        return [
            'momentum' => (new MomentumNudge($this->candidate, $this->entitlements))
                ->build($cohort, $mockHistory, $live),
            'verification' => (new VerificationProfile($this->candidate))->summary(),
            'applications' => $this->applications($applications),
            'funnel' => $this->funnel($applications),
            'mock_history' => $mockHistory,
            'profile_completeness' => $this->completeness(),
            'credits' => [
                'mock' => $this->entitlements->balance($this->candidate, EntitlementFeature::VoiceMock->value),
                'cv' => $this->entitlements->balance($this->candidate, EntitlementFeature::Cv->value),
            ],
            'cohort' => $cohort,
        ];
    }

    /**
     * @param  Collection<int, EmployerJobApplication>  $applications
     * @return list<array<string, mixed>>
     */
    private function applications($applications): array
    {
        return $applications->map(fn (EmployerJobApplication $a): array => [
            'id' => $a->id,
            'job_id' => $a->employer_job_id,
            'title' => $a->job?->title,
            'company' => $a->job?->workspace?->name,
            'stage' => $a->stage instanceof EmployerApplicationStage ? $a->stage->value : (string) $a->stage,
            'mock_score' => $a->mock_score,
            'mock_attempts' => $a->mock_attempts,
            'graded_at' => $a->graded_at?->toIso8601String(),
            'applied_at' => $a->created_at?->toIso8601String(),
            // A candidate should always know whether the ball is in their court.
            'awaiting_candidate' => $a->mock_score === null,
        ])->values()->all();
    }

    /**
     * @param  Collection<int, EmployerJobApplication>  $applications
     * @return array<string, int>
     */
    private function funnel($applications): array
    {
        $counts = [];
        foreach ($applications as $a) {
            $stage = $a->stage instanceof EmployerApplicationStage ? $a->stage->value : (string) $a->stage;
            $counts[$stage] = ($counts[$stage] ?? 0) + 1;
        }

        return $counts;
    }

    /**
     * Score trajectory across every graded mock, so improvement is visible.
     *
     * @return list<array{date: string, score: int, role: string|null}>
     */
    private function mockHistory(): array
    {
        return MockInterview::query()
            ->where('user_id', $this->candidate->id)
            ->where('status', MockInterview::STATUS_COMPLETED)
            ->whereNotNull('overall_score')
            ->with('blueprint:id,role_title')
            ->orderBy('completed_at')
            ->limit(40)
            ->get()
            ->map(fn (MockInterview $m): array => [
                'date' => $m->completed_at?->toDateString() ?? '',
                'score' => (int) $m->overall_score,
                'role' => $m->blueprint?->role_title,
            ])
            ->values()
            ->all();
    }

    /**
     * What is actually missing from the profile an employer opens. Concrete
     * and fixable — never a score out of ten with no way to move it.
     *
     * @return array{pct: int, items: list<array{key: string, label: string, done: bool}>}
     */
    private function completeness(): array
    {
        $profile = CvProfile::query()->where('user_id', $this->candidate->id)->first();
        $data = is_array($profile?->data) ? $profile->data : [];

        $items = [
            ['key' => 'resume', 'label' => 'Resume uploaded and parsed', 'done' => $profile !== null],
            ['key' => 'skills', 'label' => 'Skills listed', 'done' => ! empty($data['skills'] ?? [])],
            ['key' => 'experience', 'label' => 'Work history added', 'done' => ! empty($data['experience'] ?? [])],
            ['key' => 'education', 'label' => 'Education added', 'done' => ! empty($data['education'] ?? [])],
            ['key' => 'summary', 'label' => 'Profile summary written', 'done' => ! empty($data['summary'] ?? null)],
            ['key' => 'mock', 'label' => 'At least one graded mock', 'done' => MockInterview::query()
                ->where('user_id', $this->candidate->id)
                ->whereNotNull('overall_score')
                ->exists()],
        ];

        $done = count(array_filter($items, fn (array $i): bool => $i['done']));

        return [
            'pct' => (int) round(($done / count($items)) * 100),
            'items' => $items,
        ];
    }

    /**
     * Anonymised comparison against candidates who reached an offer in this
     * tenant — the same approach as OutcomeCohort (PRD §6.21): no names, no
     * ids, aggregate only, and null when the sample is too thin.
     *
     * This is what replaces an invented "someone got an offer because they
     * bought a package" nudge. It is a real, checkable gap, which is both
     * more honest and more persuasive.
     *
     * @return array{offer_median_mock: int, your_best_mock: int|null, sample: int, verified_share_pct: int}|null
     */
    private function cohort(): ?array
    {
        $offerStages = [
            EmployerApplicationStage::Offer->value,
            EmployerApplicationStage::Hired->value,
        ];

        $scores = EmployerJobApplication::query()
            ->whereIn('stage', $offerStages)
            ->whereNotNull('mock_score')
            ->pluck('mock_score')
            ->map(fn ($s): int => (int) $s)
            ->sort()
            ->values()
            ->all();

        if (count($scores) < self::MIN_COHORT) {
            return null;
        }

        $mid = intdiv(count($scores), 2);
        $median = count($scores) % 2 === 0
            ? (int) round(($scores[$mid - 1] + $scores[$mid]) / 2)
            : $scores[$mid];

        $best = MockInterview::query()
            ->where('user_id', $this->candidate->id)
            ->whereNotNull('overall_score')
            ->max('overall_score');

        $verifiedShare = (new CohortVerificationShare)->pctAmongStages($offerStages);

        return [
            'offer_median_mock' => $median,
            'your_best_mock' => $best !== null ? (int) $best : null,
            'sample' => count($scores),
            'verified_share_pct' => $verifiedShare,
        ];
    }
}
