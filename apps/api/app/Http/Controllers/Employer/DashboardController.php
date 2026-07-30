<?php

declare(strict_types=1);

namespace App\Http\Controllers\Employer;

use App\Enums\EmployerApplicationStage;
use App\Enums\EmployerJobStatus;
use App\Http\Controllers\Controller;
use App\Models\EmployerInterview;
use App\Models\EmployerJobApplication;
use App\Models\EmployerWorkspace;
use App\Support\Employers\ResolvesMembership;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * One-round-trip dashboard aggregates (PRD-E §5.2): the mono stat band,
 * per-JD pipeline pulse, and the data behind Next Best Action.
 */
final class DashboardController extends Controller
{
    use ResolvesMembership;

    public function show(Request $request, EmployerWorkspace $workspace): JsonResponse
    {
        $this->membershipOrFail($workspace, $request->user());

        $jobIds = $workspace->jobs()->pluck('id');
        $applications = EmployerJobApplication::query()->whereIn('employer_job_id', $jobIds);

        $stageCounts = (clone $applications)
            ->selectRaw('employer_job_id, stage, count(*) as total')
            ->groupBy('employer_job_id', 'stage')
            ->get()
            ->groupBy('employer_job_id');

        $pipeline = $workspace->jobs()
            ->where('status', EmployerJobStatus::Published->value)
            ->orderByDesc('published_at')
            ->get(['id', 'title', 'published_at'])
            ->map(function ($job) use ($stageCounts): array {
                $counts = [];
                foreach ($stageCounts->get($job->id, collect()) as $row) {
                    // Grouped rows hydrate as models, so stage arrives enum-cast.
                    $stage = $row->stage instanceof EmployerApplicationStage ? $row->stage->value : (string) $row->stage;
                    $counts[$stage] = (int) $row->total;
                }

                return [
                    'id' => $job->id,
                    'title' => $job->title,
                    'published_at' => $job->published_at?->toIso8601String(),
                    'stage_counts' => $counts,
                    'awaiting_review' => $counts[EmployerApplicationStage::Graded->value] ?? 0,
                ];
            })
            ->values();

        return response()->json(['data' => [
            'funnel' => $this->funnel(clone $applications),
            'trend' => $this->trend(clone $applications),
            'score_distribution' => $this->scoreDistribution(clone $applications),
            'active_jobs' => $workspace->jobs()->where('status', EmployerJobStatus::Published->value)->count(),
            'total_applications' => (clone $applications)->count(),
            'graded_applications' => (clone $applications)->whereNotNull('graded_at')->count(),
            'graded_last_7d' => (clone $applications)->where('graded_at', '>=', now()->subDays(7))->count(),
            'awaiting_review' => (clone $applications)->where('stage', EmployerApplicationStage::Graded->value)->count(),
            'interviews_in_flight' => EmployerInterview::query()
                ->whereIn('employer_job_application_id', (clone $applications)->select('id'))
                ->whereIn('status', ['invited', 'in_progress', 'submitted'])
                ->count(),
            'offers_open' => (clone $applications)->where('stage', EmployerApplicationStage::Offer->value)->count(),
            'hired' => (clone $applications)->where('stage', EmployerApplicationStage::Hired->value)->count(),
            'pipeline' => $pipeline,
        ]]);
    }

    /**
     * Stage funnel with drop-off, for the funnel chart. Stages are
     * cumulative: a candidate at L2 also cleared Shortlisted, so each
     * bar counts everyone who reached that stage or beyond — otherwise
     * the funnel reads as if candidates vanished.
     *
     * @param  Builder<EmployerJobApplication>  $applications
     * @return list<array{stage: string, label: string, count: int}>
     */
    private function funnel($applications): array
    {
        $counts = $applications
            ->selectRaw('stage, count(*) as total')
            ->groupBy('stage')
            ->pluck('total', 'stage')
            ->all();

        $normalized = [];
        foreach ($counts as $stage => $total) {
            $key = $stage instanceof EmployerApplicationStage ? $stage->value : (string) $stage;
            $normalized[$key] = (int) $total;
        }

        $order = [
            EmployerApplicationStage::Applied,
            EmployerApplicationStage::Graded,
            EmployerApplicationStage::Shortlisted,
            EmployerApplicationStage::L1,
            EmployerApplicationStage::L2,
            EmployerApplicationStage::Offer,
            EmployerApplicationStage::Hired,
        ];

        $labels = [
            'applied' => 'Applied',
            'graded' => 'Graded',
            'shortlisted' => 'Shortlisted',
            'l1' => 'L1 done',
            'l2' => 'L2 done',
            'offer' => 'Offer',
            'hired' => 'Hired',
        ];

        $out = [];
        foreach ($order as $index => $stage) {
            $reached = 0;
            foreach (array_slice($order, $index) as $laterStage) {
                $reached += $normalized[$laterStage->value] ?? 0;
            }

            $out[] = [
                'stage' => $stage->value,
                'label' => $labels[$stage->value],
                'count' => $reached,
            ];
        }

        return $out;
    }

    /**
     * Daily applications vs gradings for the last 14 days — the trend
     * sparkline. Zero-filled so the line never has gaps.
     *
     * @param  Builder<EmployerJobApplication>  $applications
     * @return list<array{date: string, applications: int, graded: int}>
     */
    private function trend($applications): array
    {
        $since = now()->subDays(13)->startOfDay();
        $rows = $applications->where('created_at', '>=', $since)->get(['created_at', 'graded_at']);

        $days = [];
        for ($i = 13; $i >= 0; $i--) {
            $days[now()->subDays($i)->toDateString()] = ['applications' => 0, 'graded' => 0];
        }

        foreach ($rows as $row) {
            $applied = $row->created_at?->toDateString();
            if ($applied !== null && isset($days[$applied])) {
                $days[$applied]['applications']++;
            }

            $graded = $row->graded_at?->toDateString();
            if ($graded !== null && isset($days[$graded])) {
                $days[$graded]['graded']++;
            }
        }

        $out = [];
        foreach ($days as $date => $counts) {
            $out[] = ['date' => $date, ...$counts];
        }

        return $out;
    }

    /**
     * Mock-score histogram in ten-point bands, for the distribution
     * chart that tells an employer where to set an automation threshold.
     *
     * @param  Builder<EmployerJobApplication>  $applications
     * @return list<array{band: string, floor: int, count: int}>
     */
    private function scoreDistribution($applications): array
    {
        $scores = $applications->whereNotNull('mock_score')->pluck('mock_score');

        $bands = [];
        for ($floor = 0; $floor <= 90; $floor += 10) {
            $bands[$floor] = 0;
        }

        foreach ($scores as $score) {
            $floor = min(90, (int) floor(((int) $score) / 10) * 10);
            $bands[$floor]++;
        }

        $out = [];
        foreach ($bands as $floor => $count) {
            $out[] = ['band' => $floor.'–'.($floor + 9), 'floor' => $floor, 'count' => $count];
        }

        return $out;
    }
}
