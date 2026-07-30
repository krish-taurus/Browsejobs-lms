<?php

declare(strict_types=1);

namespace App\Http\Controllers\Me;

use App\Actions\Employers\ApplyToEmployerJob;
use App\Actions\Employers\StartEmployerJobMock;
use App\Enums\EmployerJobStatus;
use App\Enums\EntitlementFeature;
use App\Http\Controllers\Controller;
use App\Http\Requests\Employers\ApplyToJobRequest;
use App\Http\Resources\CandidateApplicationResource;
use App\Http\Resources\PublicEmployerJobResource;
use App\Models\EmployerJob;
use App\Models\EmployerJobApplication;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Candidate-side surface for employer JDs (PRD-E F3): browse published
 * roles, apply free, track own applications. Tenant scoping comes from
 * the authenticated user.
 */
final class EmployerJobBrowseController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $jobs = EmployerJob::query()
            ->where('status', EmployerJobStatus::Published->value)
            ->with('workspace')
            ->when($request->filled('q'), function ($query) use ($request): void {
                $term = '%'.$request->string('q')->toString().'%';
                $query->where(fn ($inner) => $inner->where('title', 'like', $term)->orWhere('role_family', 'like', $term));
            })
            ->orderByDesc('published_at')
            ->paginate(20);

        return PublicEmployerJobResource::collection($jobs)->response();
    }

    public function show(Request $request, EmployerJob $job): JsonResponse
    {
        abort_unless($job->status === EmployerJobStatus::Published, 404);

        return (new PublicEmployerJobResource($job->load('workspace')))->response();
    }

    public function apply(ApplyToJobRequest $request, EmployerJob $job, ApplyToEmployerJob $apply): JsonResponse
    {
        $application = $apply->handle(
            $job,
            $request->user(),
            $request->validated()['knockout_answers'] ?? null,
        );

        return (new CandidateApplicationResource($application->load('job')))->response()->setStatusCode(201);
    }

    /**
     * Start (or resume) this JD's mock. Spends one mock credit per fresh
     * attempt; retakes are unlimited while the package holds and the best
     * attempt is the one the employer ranks on.
     */
    public function mock(Request $request, EmployerJob $job, StartEmployerJobMock $start): JsonResponse
    {
        abort_unless($job->status === EmployerJobStatus::Published, 404);

        $user = $request->user();

        try {
            $interview = $start->handle($user, $job);
        } catch (ValidationException $e) {
            // An empty wallet is not a validation failure to the candidate —
            // it is an offer. 402 carries the packs that unlock a retake.
            if (array_key_exists('feature', $e->errors())) {
                return response()->json([
                    'error' => [
                        'code' => 'mock_credits_required',
                        'message' => 'You are out of mock credits for this role.',
                        'offers' => Product::query()
                            ->where('feature', EntitlementFeature::VoiceMock->value)
                            ->where('active', true)
                            ->get(['sku', 'name', 'price_paise', 'grant_amount'])
                            ->all(),
                    ],
                ], 402);
            }

            throw $e;
        }

        return response()->json(['data' => ['mock_id' => $interview->id]], 201);
    }

    public function applications(Request $request): JsonResponse
    {
        $applications = EmployerJobApplication::query()
            ->where('candidate_id', $request->user()->id)
            ->with('job')
            ->latest()
            ->paginate(20);

        return CandidateApplicationResource::collection($applications)->response();
    }
}
