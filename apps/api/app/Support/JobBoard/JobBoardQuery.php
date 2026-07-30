<?php

declare(strict_types=1);

namespace App\Support\JobBoard;

use App\Enums\EmployerJobStatus;
use App\Models\EmployerJob;
use App\Models\EmployerJobApplication;
use App\Models\JobFeedItem;
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

        return $jobs->map(fn (EmployerJob $job): array => [
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
            // What makes an internal posting different, stated plainly.
            'mock_ready' => $job->currentMock() !== null,
            'has_applied' => in_array($job->id, $applied, true),
        ])->all();
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
}
