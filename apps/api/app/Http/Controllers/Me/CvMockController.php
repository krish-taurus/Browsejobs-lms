<?php

declare(strict_types=1);

namespace App\Http\Controllers\Me;

use App\Actions\Cv\StartCvReadinessMock;
use App\Http\Controllers\Controller;
use App\Models\MockInterview;
use App\Support\Entitlements\EntitlementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The AI Readiness Interview (candidate request, Aug 2026): a general,
 * CV-driven mock, not tied to any job. Mirrors EmployerJobBrowseController's
 * mock()/myMock() pair exactly — the interview room itself
 * ((portal)/mock/[id]/room) and the answer/finish/abandon endpoints under
 * MockController are already transport- and blueprint-agnostic, so nothing
 * new is needed there; this controller only has to start one and report on it.
 */
final class CvMockController extends Controller
{
    public function store(Request $request, StartCvReadinessMock $start): JsonResponse
    {
        $interview = $start->handle($request->user());

        return response()->json(['data' => ['mock_id' => $interview->id]], 201);
    }

    /**
     * Where the candidate stands — nothing started, an attempt to resume, or
     * a completed one with its score — same shape as myMock() so the
     * frontend can reuse the same status-driven card pattern.
     */
    public function show(Request $request, EntitlementService $entitlements): JsonResponse
    {
        $all = MockInterview::query()
            ->where('user_id', $request->user()->id)
            ->whereHas('blueprint', fn ($q) => $q->where('user_id', $request->user()->id))
            ->get();

        $attempts = [
            'used' => $all->count(),
            'limit' => $entitlements->settings()->cv_mock_attempts_limit,
        ];

        $completed = $all->where('status', MockInterview::STATUS_COMPLETED)->sortByDesc('overall_score')->first();
        if ($completed !== null) {
            return response()->json(['data' => [
                'status' => 'completed',
                'mock_id' => $completed->id,
                'score' => $completed->overall_score,
                'attempts' => $attempts,
                'has_recording' => $completed->recording_url !== null,
            ]]);
        }

        $inProgress = $all->firstWhere('status', MockInterview::STATUS_IN_PROGRESS);
        if ($inProgress !== null) {
            return response()->json(['data' => ['status' => 'in_progress', 'mock_id' => $inProgress->id, 'score' => null, 'attempts' => $attempts]]);
        }

        return response()->json(['data' => ['status' => 'none', 'mock_id' => null, 'score' => null, 'attempts' => $attempts]]);
    }
}
