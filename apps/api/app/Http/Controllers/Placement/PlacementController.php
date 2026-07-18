<?php

declare(strict_types=1);

namespace App\Http\Controllers\Placement;

use App\Actions\Cv\GenerateCv;
use App\Actions\Placement\ApplyToJob;
use App\Actions\Placement\RecordInterviewRound;
use App\Http\Controllers\Controller;
use App\Models\CvDocument;
use App\Models\JobApplication;
use App\Models\JobPosting;
use App\Models\MentorProfile;
use App\Support\Placement\PlacementPool;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Student placement surface (PRD §6.11): the pool checklist, the job board
 * (their courses + open-to-all postings), applications with per-round
 * debrief capture, and withdraw. Under auth:sanctum without tenant.user.
 */
final class PlacementController extends Controller
{
    public function __construct(
        private readonly PlacementPool $pool,
        private readonly ApplyToJob $apply,
        private readonly RecordInterviewRound $rounds,
    ) {}

    public function index(Request $request): JsonResponse
    {
        return app(TenantContext::class)->run($request->user()->tenant, function () use ($request): JsonResponse {
            $student = $request->user();
            $courseIds = MentorProfile::courseIdsFor($student);

            $jobs = JobPosting::query()
                ->where('status', JobPosting::STATUS_OPEN)
                ->where(fn ($q) => $q->whereNull('course_id')->orWhereIn('course_id', $courseIds))
                ->where(fn ($q) => $q->whereNull('closes_on')->orWhere('closes_on', '>=', now()->toDateString()))
                ->orderByDesc('id')
                ->get();

            $applications = JobApplication::query()
                ->where('user_id', $student->id)
                ->with(['posting:id,title,company,location', 'rounds' => fn ($q) => $q->orderBy('round_no')])
                ->orderByDesc('id')
                ->get();

            $appliedPostingIds = $applications->pluck('job_posting_id')->flip();

            return response()->json(['data' => [
                'pool' => $this->pool->statusFor($student),
                'jobs' => $jobs->map(fn (JobPosting $j) => [
                    'id' => $j->id,
                    'title' => $j->title,
                    'company' => $j->company,
                    'location' => $j->location,
                    'work_mode' => $j->work_mode,
                    'salary_range' => $j->salary_range,
                    'description' => $j->description,
                    'closes_on' => $j->closes_on?->toDateString(),
                    'applied' => $appliedPostingIds->has($j->id),
                ]),
                'applications' => $applications->map(fn (JobApplication $a) => $this->row($a)),
            ]]);
        });
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'job_posting_id' => ['required', 'integer'],
            'cv_document_id' => ['nullable', 'integer'],
            'celebrate_consent' => ['sometimes', 'boolean'],
            'celebrate_anonymous' => ['sometimes', 'boolean'],
        ]);

        return app(TenantContext::class)->run($request->user()->tenant, function () use ($request, $validated): JsonResponse {
            $application = $this->apply->handle(
                $request->user(),
                (int) $validated['job_posting_id'],
                isset($validated['cv_document_id']) ? (int) $validated['cv_document_id'] : null,
                (bool) ($validated['celebrate_consent'] ?? false),
                (bool) ($validated['celebrate_anonymous'] ?? false),
            );

            return response()->json(['data' => $this->row($application->load('posting:id,title,company,location'))], 201);
        });
    }

    public function storeRound(Request $request, int $application): JsonResponse
    {
        $validated = $request->validate([
            'round_type' => ['nullable', 'string', 'max:40'],
            'happened_on' => ['required', 'date'],
            'outcome' => ['required', 'in:advanced,rejected,pending,offer'],
            'debrief' => ['nullable', 'string', 'max:20000'],
            'share_debrief' => ['sometimes', 'boolean'],
        ]);

        return app(TenantContext::class)->run($request->user()->tenant, function () use ($request, $application, $validated): JsonResponse {
            $model = $this->owned($request, $application);

            $this->rounds->handle($model, [
                'round_type' => $validated['round_type'] ?? null,
                'happened_on' => $validated['happened_on'],
                'outcome' => $validated['outcome'],
                'debrief' => $validated['debrief'] ?? null,
                'share_debrief' => (bool) ($validated['share_debrief'] ?? false),
            ]);

            return response()->json(['data' => $this->row($model->refresh()->load(['posting:id,title,company,location', 'rounds']))], 201);
        });
    }

    /**
     * Career+ AI CV tailoring for one application (PRD §6.20): generate a JD-tailored CV
     * from the posting's description and attach it. Gated by the `career-plus` middleware;
     * spends no CV credit — CV refresh is part of the subscription.
     */
    public function tailor(Request $request, int $application, GenerateCv $cv): JsonResponse
    {
        return app(TenantContext::class)->run($request->user()->tenant, function () use ($request, $application, $cv): JsonResponse {
            $model = $this->owned($request, $application);
            $posting = $model->posting()->first();

            abort_if($posting === null || trim((string) $posting->description) === '', 422, 'This job has no description to tailor against.');

            $document = $cv->handle($request->user(), CvDocument::SOURCE_TAILORED, $posting->description, $posting->course_id);
            $model->update(['cv_document_id' => $document->id]);

            return response()->json(['data' => [
                'cv_document_id' => $document->id,
                'title' => $document->title,
                'ats_score' => $document->ats['score'] ?? null,
            ]]);
        });
    }

    public function withdraw(Request $request, int $application): JsonResponse
    {
        return app(TenantContext::class)->run($request->user()->tenant, function () use ($request, $application): JsonResponse {
            $model = $this->owned($request, $application);

            abort_if(in_array($model->status, [JobApplication::STATUS_PLACED, JobApplication::STATUS_WITHDRAWN], true), 422, 'This application can no longer be withdrawn.');

            $model->update(['status' => JobApplication::STATUS_WITHDRAWN]);

            return response()->json(['data' => $this->row($model->refresh())]);
        });
    }

    private function owned(Request $request, int $id): JobApplication
    {
        return JobApplication::query()
            ->where('user_id', $request->user()->id)
            ->findOrFail($id);
    }

    /**
     * @return array<string, mixed>
     */
    private function row(JobApplication $a): array
    {
        return [
            'id' => $a->id,
            'posting' => [
                'title' => $a->posting?->title,
                'company' => $a->posting?->company,
                'location' => $a->posting?->location,
            ],
            'status' => $a->status,
            'placed_at' => $a->placed_at?->toIso8601String(),
            'rounds' => $a->rounds->map(fn ($r) => [
                'round_no' => $r->round_no,
                'round_type' => $r->round_type,
                'happened_on' => $r->happened_on->toDateString(),
                'outcome' => $r->outcome,
                'shared' => $r->interview_transcript_id !== null,
            ])->values(),
        ];
    }
}
