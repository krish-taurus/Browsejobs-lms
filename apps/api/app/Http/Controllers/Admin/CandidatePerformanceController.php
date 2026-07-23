<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Batch;
use App\Models\MockInterview;
use App\Models\StudentScore;
use App\Models\User;
use App\Support\Scoring\CandidatePerformance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Candidate Performance dashboard (ADR 0050): batch-wise candidates ranked by
 * the founder's weighted score (attendance 50% > mocks 30% > assignments 20%),
 * banded green >= 80 / amber 60-79.9 / red < 60. Filter by batch or name; click
 * a candidate for their component breakdown and per-module mastery.
 */
final class CandidatePerformanceController extends Controller
{
    public function index(Request $request, CandidatePerformance $performance): JsonResponse
    {
        $batches = Batch::query()->orderByDesc('id')->get(['id', 'number']);

        $students = User::query()
            ->where('user_type', 'student')
            ->when($request->filled('batch_id'), fn ($q) => $q->whereHas(
                'batchMemberships',
                fn ($m) => $m->where('batch_id', (int) $request->input('batch_id')),
            ))
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w
                ->where('name', 'like', '%'.$request->string('q')->toString().'%')
                ->orWhere('email', 'like', '%'.$request->string('q')->toString().'%')))
            ->with('batchMemberships.batch:id,number')
            ->limit(200)
            ->get();

        $rows = $students
            ->map(function (User $student) use ($performance): array {
                $c = $performance->componentsFor($student);

                return [
                    'id' => $student->id,
                    'name' => $student->name,
                    'email' => $student->email,
                    'batches' => $student->batchMemberships->map(fn ($m) => $m->batch?->number)->filter()->values(),
                    ...$c,
                ];
            })
            ->sortByDesc('total')
            ->values()
            ->map(fn (array $row, int $i) => [...$row, 'rank' => $i + 1]);

        return response()->json(['data' => [
            'batches' => $batches->map(fn (Batch $b) => ['id' => $b->id, 'number' => $b->number]),
            'weights' => ['attendance' => CandidatePerformance::W_ATTENDANCE, 'mocks' => CandidatePerformance::W_MOCKS, 'assignments' => CandidatePerformance::W_ASSIGNMENTS],
            'candidates' => $rows,
        ]]);
    }

    public function show(User $user, CandidatePerformance $performance): JsonResponse
    {
        abort_unless($user->user_type === 'student', 404);

        $score = StudentScore::query()->where('user_id', $user->id)->first();
        $mocks = MockInterview::query()
            ->where('user_id', $user->id)->where('status', 'completed')
            ->orderByDesc('completed_at')->limit(10)
            ->get(['id', 'mode', 'overall_score', 'completed_at']);

        return response()->json(['data' => [
            'candidate' => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email],
            'components' => $performance->componentsFor($user),
            'pri' => $score?->pri,
            'engagement' => $score?->engagement,
            'mastery' => $score?->mastery ?? [],
            'recent_mocks' => $mocks->map(fn (MockInterview $m) => [
                'id' => $m->id, 'mode' => $m->mode, 'score' => $m->overall_score,
                'completed_at' => $m->completed_at?->toDateString(),
            ]),
        ]]);
    }
}
