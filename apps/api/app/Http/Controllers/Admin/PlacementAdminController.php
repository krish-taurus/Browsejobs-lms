<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Placement\UpdateApplicationStatus;
use App\Http\Controllers\Controller;
use App\Models\JobApplication;
use App\Models\JobPosting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Placement-officer workspace (PRD §6.11): the application pipeline board,
 * job-posting management, and offer/placement recording. Behind
 * can:manage-placements.
 */
final class PlacementAdminController extends Controller
{
    public function __construct(private readonly UpdateApplicationStatus $update) {}

    public function index(): JsonResponse
    {
        $applications = JobApplication::query()
            ->with(['student:id,name', 'posting:id,title,company', 'rounds' => fn ($q) => $q->orderBy('round_no')])
            ->orderByDesc('updated_at')
            ->limit(200)
            ->get()
            ->map(fn (JobApplication $a) => [
                'id' => $a->id,
                'student' => $a->student?->name,
                'posting' => "{$a->posting?->title} · {$a->posting?->company}",
                'status' => $a->status,
                'note' => $a->note,
                'offer_ctc_paise' => $a->offer_ctc_paise,
                'offer_role' => $a->offer_role,
                'placed_at' => $a->placed_at?->toIso8601String(),
                'celebrate_consent' => $a->celebrate_consent_at !== null,
                'rounds' => $a->rounds->map(fn ($r) => [
                    'round_no' => $r->round_no,
                    'round_type' => $r->round_type,
                    'outcome' => $r->outcome,
                    'debrief' => $r->debrief,
                    'shared' => $r->interview_transcript_id !== null,
                ])->values(),
            ])
            ->groupBy('status');

        $jobs = JobPosting::query()->withCount('applications')->orderByDesc('id')->get();

        return response()->json(['data' => [
            'pipeline' => JobApplication::PIPELINE,
            'board' => $applications,
            'jobs' => $jobs,
            'stats' => [
                'placed' => JobApplication::query()->where('status', JobApplication::STATUS_PLACED)->count(),
                'offers' => JobApplication::query()->whereIn('status', [JobApplication::STATUS_OFFER, JobApplication::STATUS_PLACED])->count(),
                'active' => JobApplication::query()->whereIn('status', [
                    JobApplication::STATUS_APPLIED, JobApplication::STATUS_SHORTLISTED, JobApplication::STATUS_INTERVIEWING,
                ])->count(),
            ],
        ]]);
    }

    public function storeJob(Request $request): JsonResponse
    {
        $validated = $this->jobRules($request);

        $job = JobPosting::query()->create([
            ...$validated,
            'tenant_id' => $request->user()->tenant_id,
            'posted_by' => $request->user()->id,
            'status' => JobPosting::STATUS_OPEN,
        ]);

        return response()->json(['data' => $job], 201);
    }

    public function updateJob(Request $request, JobPosting $job): JsonResponse
    {
        $validated = $this->jobRules($request, updating: true);

        if ($request->filled('status')) {
            $validated['status'] = $request->validate(['status' => 'in:open,closed'])['status'];
        }

        $job->update($validated);

        return response()->json(['data' => $job->refresh()]);
    }

    public function updateApplication(Request $request, JobApplication $application): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'string'],
            'offer_ctc_paise' => ['nullable', 'integer', 'min:0'],
            'offer_role' => ['nullable', 'string', 'max:150'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $this->update->handle($request->user(), $application, $validated);

        return response()->json(['data' => [
            'id' => $application->id,
            'status' => $application->refresh()->status,
            'placed_at' => $application->placed_at?->toIso8601String(),
        ]]);
    }

    /**
     * @return array<string, mixed>
     */
    private function jobRules(Request $request, bool $updating = false): array
    {
        $required = $updating ? 'sometimes' : 'required';

        return $request->validate([
            'title' => [$required, 'string', 'max:190'],
            'company' => [$required, 'string', 'max:190'],
            'description' => [$required, 'string', 'max:10000'],
            'course_id' => ['nullable', 'integer'],
            'location' => ['nullable', 'string', 'max:190'],
            'work_mode' => ['nullable', 'in:onsite,hybrid,remote'],
            'salary_range' => ['nullable', 'string', 'max:100'],
            'apply_url' => ['nullable', 'url', 'max:500'],
            'closes_on' => ['nullable', 'date'],
        ]);
    }
}
