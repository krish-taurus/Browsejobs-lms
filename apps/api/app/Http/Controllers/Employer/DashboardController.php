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
}
