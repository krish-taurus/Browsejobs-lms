<?php

declare(strict_types=1);

namespace App\Support\JobBoard;

use App\Enums\EmployerJobStatus;
use App\Models\EmployerJob;
use App\Models\EmployerJobApplication;
use App\Models\JobFeedItem;
use App\Models\MockInterview;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * The one job board (PRD-E F10). Two segments that behave differently and
 * are never blended, because the difference is the whole point:
 *
 * - `internal` — employers hiring through BrowseJobs. The candidate applies
 *   here, sits that JD's mock, and the employer sees a graded profile. We
 *   own the outcome, so we can say what happens next.
 * - `external` — roles aggregated from the wider market. We prepare the
 *   candidate and hand them off; the application happens on someone else's
 *   site. We never imply we can influence it.
 *
 * Presenting these as one undifferentiated list would be the dishonest
 * option: it would let an aggregated posting borrow the credibility of a
 * real pipeline. Hence two segments, labelled.
 */
final readonly class JobBoardQuery
{
    public function __construct(private ?User $viewer = null) {}

    /**
     * @return array{internal: list<array<string, mixed>>, external: list<array<string, mixed>>, counts: array{internal: int, external: int}}
     */
    public function handle(?string $term = null, int $perSegment = 24): array
    {
        return [
            'internal' => $this->internal($term, $perSegment),
            'external' => $this->external($term, $perSegment),
            'counts' => [
                'internal' => $this->internalBase($term)->count(),
                'external' => $this->externalBase($term)->count(),
            ],
        ];
    }

    /** @return Builder<EmployerJob> */
    private function internalBase(?string $term)
    {
        return EmployerJob::query()
            ->where('status', EmployerJobStatus::Published->value)
            ->when($term, function ($query) use ($term): void {
                $like = '%'.$term.'%';
                $query->where(fn ($inner) => $inner
                    ->where('title', 'like', $like)
                    ->orWhere('role_family', 'like', $like));
            });
    }

    /** @return Builder<JobFeedItem> */
    private function externalBase(?string $term)
    {
        return JobFeedItem::query()
            ->where('status', JobFeedItem::STATUS_ACTIVE)
            ->when($term, function ($query) use ($term): void {
                $like = '%'.$term.'%';
                $query->where(fn ($inner) => $inner
                    ->where('title', 'like', $like)
                    ->orWhere('company', 'like', $like)
                    ->orWhere('role_title', 'like', $like));
            });
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function internal(?string $term, int $limit): array
    {
        $jobs = $this->internalBase($term)
            ->with(['workspace', 'mocks'])
            ->orderByDesc('published_at')
            ->limit($limit)
            ->get();

        $applied = $this->appliedJobIds($jobs->pluck('id')->all());
        $mockStatuses = $this->mockStatusesByJob($jobs->pluck('id')->all());

        return $jobs->map(function (EmployerJob $job) use ($applied, $mockStatuses): array {
            $mock = $mockStatuses[$job->id] ?? ['status' => 'none', 'score' => null];

            return [
                'segment' => 'internal',
                'id' => $job->id,
                'title' => $job->title,
                'company' => $job->workspace?->name,
                'locations' => $job->locations ?? [],
                'remote' => (bool) $job->remote,
                'skills' => array_slice($job->skills ?? [], 0, 8),
                'experience_min_years' => $job->experience_min_years,
                'experience_max_years' => $job->experience_max_years,
                'openings' => $job->openings,
                'posted_at' => $job->published_at?->toIso8601String(),
                'description' => $job->description,
                // CTC only when the employer chose to publish it. An absent
                // figure is not a gap the public board is allowed to fill.
                ...($job->ctc_visible ? [
                    'ctc_min_paise' => $job->ctc_min_paise,
                    'ctc_max_paise' => $job->ctc_max_paise,
                ] : []),
                // What makes an internal posting different, stated plainly.
                'mock_ready' => $job->currentMock() !== null,
                'has_applied' => in_array($job->id, $applied, true),
                // Where the viewer stands on THIS job's own interview — the
                // step Apply is gated behind (PRD-E F3) — so the card can
                // show "Take AI interview" / "Resume" / "Apply" instead of
                // always "View & apply" and finding out it's blocked one
                // click later.
                'mock_status' => $mock['status'],
                'mock_score' => $mock['score'],
            ];
        })->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function external(?string $term, int $limit): array
    {
        return $this->externalBase($term)
            ->orderByDesc('posted_at')
            ->limit($limit)
            ->get()
            ->map(fn (JobFeedItem $item): array => [
                'segment' => 'external',
                'id' => $item->id,
                'title' => $item->title,
                'company' => $item->company,
                'location' => $item->location,
                'work_mode' => $item->work_mode,
                'skills' => array_slice($item->extracted_skills ?? [], 0, 8),
                'seniority' => $item->seniority,
                'posted_at' => $item->posted_at?->toIso8601String(),
                'expires_at' => $item->expires_at?->toIso8601String(),
                'description' => $item->description,
                'source_kind' => $item->source_kind,
                'question_count' => is_array($item->prep_questions) ? count($item->prep_questions) : 0,
            ])->all();
    }

    /**
     * @param  list<int>  $jobIds
     * @return list<int>
     */
    private function appliedJobIds(array $jobIds): array
    {
        if ($this->viewer === null || $jobIds === []) {
            return [];
        }

        return EmployerJobApplication::query()
            ->where('candidate_id', $this->viewer->id)
            ->whereIn('employer_job_id', $jobIds)
            ->pluck('employer_job_id')
            ->all();
    }

    /**
     * The viewer's own interview status against each of these JDs, in one
     * query rather than one per card (mirrors EmployerJobBrowseController's
     * per-job myMock(), batched — a completed attempt always wins over a
     * stale in-progress row from an earlier retake, same rule).
     *
     * @param  list<int>  $jobIds
     * @return array<int, array{status: string, score: int|null}>
     */
    private function mockStatusesByJob(array $jobIds): array
    {
        if ($this->viewer === null || $jobIds === []) {
            return [];
        }

        $interviews = MockInterview::query()
            ->where('user_id', $this->viewer->id)
            ->whereHas('blueprint', fn ($q) => $q->whereIn('employer_job_id', $jobIds))
            ->with('blueprint:id,employer_job_id')
            ->get()
            ->groupBy(fn (MockInterview $m) => $m->blueprint?->employer_job_id);

        $statuses = [];
        foreach ($interviews as $jobId => $attempts) {
            $completed = $attempts->where('status', MockInterview::STATUS_COMPLETED)->sortByDesc('overall_score')->first();
            if ($completed !== null) {
                $statuses[$jobId] = ['status' => 'completed', 'score' => $completed->overall_score];

                continue;
            }

            $inProgress = $attempts->firstWhere('status', MockInterview::STATUS_IN_PROGRESS);
            $statuses[$jobId] = $inProgress !== null
                ? ['status' => 'in_progress', 'score' => null]
                : ['status' => 'none', 'score' => null];
        }

        return $statuses;
    }
}
