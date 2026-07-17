<?php

declare(strict_types=1);

namespace App\Http\Controllers\Me;

use App\Http\Controllers\Controller;
use App\Http\Requests\Me\LeaderboardPreferenceRequest;
use App\Models\Batch;
use App\Models\PointsEvent;
use App\Models\StudentBadge;
use App\Models\User;
use App\Support\Points\PointsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The batch leaderboard (PRD §6.16). Anti-toxicity by design: the top 10 are
 * named (opt-out respected — anonymised as "Student"), everyone else sees only
 * their own rank and the distance to the next rank.
 */
final class LeaderboardController extends Controller
{
    public function show(Request $request, PointsService $points): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $period = $request->query('period') === 'alltime' ? 'alltime' : 'weekly';

        $batchId = $points->activeBatchId($user);

        $badges = StudentBadge::query()
            ->where('user_id', $user->id)
            ->orderByDesc('awarded_at')
            ->get()
            ->map(fn (StudentBadge $b) => [
                'key' => $b->badge,
                'label' => $b->label(),
                'awarded_at' => $b->awarded_at->toDateString(),
            ]);

        if ($batchId === null) {
            return response()->json(['data' => [
                'batch' => null, 'period' => $period, 'top' => [], 'me' => null,
                'badges' => $badges, 'opt_out' => (bool) $user->leaderboard_opt_out,
                'participants' => 0,
            ]]);
        }

        $totals = PointsEvent::query()
            ->where('batch_id', $batchId)
            ->when($period === 'weekly', fn ($q) => $q->whereDate('awarded_on', '>=', now()->startOfWeek()->toDateString()))
            ->selectRaw('user_id, sum(points) as total')
            ->groupBy('user_id')
            ->orderByDesc('total')
            ->orderBy('user_id')
            ->get();

        $students = User::query()
            ->whereIn('id', $totals->pluck('user_id'))
            ->get(['id', 'name', 'leaderboard_opt_out'])
            ->keyBy('id');

        $top = $totals->take(10)->values()->map(function ($row, int $i) use ($students, $user) {
            $student = $students->get($row->user_id);
            $isMe = $row->user_id === $user->id;
            $named = $student !== null && ! $student->leaderboard_opt_out;

            return [
                'rank' => $i + 1,
                // Your own row is always named to you; others honour opt-out.
                'name' => $isMe ? $user->name : ($named ? $student->name : 'Student'),
                'points' => (int) $row->total,
                'is_me' => $isMe,
            ];
        });

        $me = null;
        $myIndex = $totals->search(fn ($row) => $row->user_id === $user->id);
        if ($myIndex !== false) {
            $myPoints = (int) $totals[$myIndex]->total;
            $toNext = $myIndex === 0 ? 0 : max(0, (int) $totals[$myIndex - 1]->total - $myPoints);

            $me = [
                'rank' => $myIndex + 1,
                'points' => $myPoints,
                'to_next' => $toNext,
                'coach_line' => $myIndex === 0
                    ? 'You hold #1 — keep the streak alive.'
                    : sprintf("You're %d points from #%d — one strong day closes the gap.", $toNext, $myIndex),
            ];
        }

        return response()->json(['data' => [
            'batch' => Batch::query()->whereKey($batchId)->value('number'),
            'period' => $period,
            'top' => $top,
            'me' => $me,
            'badges' => $badges,
            'opt_out' => (bool) $user->leaderboard_opt_out,
            'participants' => $totals->count(),
        ]]);
    }

    public function updatePreference(LeaderboardPreferenceRequest $request): JsonResponse
    {
        $request->user()->update(['leaderboard_opt_out' => $request->boolean('opt_out')]);

        return response()->json(['data' => ['opt_out' => $request->boolean('opt_out')]]);
    }
}
