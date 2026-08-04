<?php

declare(strict_types=1);

namespace App\Http\Controllers\Me;

use App\Actions\LiveClasses\JoinLiveSession;
use App\Enums\BatchMemberStatus;
use App\Http\Controllers\Controller;
use App\Models\BatchMember;
use App\Models\LiveSession;
use App\Support\Fees\FeeGate;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The student's live classes (PRD §6.3). Lists the sessions of every batch the student
 * is an active member of; the join endpoint hands out the Zoom link only after the
 * enrolment + fee gate passes (JoinLiveSession) — the list never carries a raw URL.
 */
final class MyClassController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return app(TenantContext::class)->run($request->user()->tenant, function () use ($request): JsonResponse {
            $batchIds = $this->batchIds($request->user()->id);

            // One fee check per batch: the gate is per-student-per-batch and a
            // student normally has a single active cohort.
            $feeOk = [];

            $sessions = LiveSession::query()
                ->whereIn('batch_id', $batchIds)
                ->with(['batch:id,number,type', 'topic:id,name', 'recordings' => fn ($q) => $q->where('status', 'stored')])
                ->orderByDesc('scheduled_start')
                ->get();

            foreach ($sessions->pluck('batch')->filter()->unique('id') as $batch) {
                $feeOk[$batch->id] = app(FeeGate::class)->allowsLiveAccess($request->user(), $batch);
            }

            return response()->json([
                'data' => $sessions->map(fn (LiveSession $s) => [
                    'id' => $s->id,
                    'title' => $s->title,
                    'kind' => $s->kind ?? LiveSession::KIND_CLASS,
                    'batch' => $s->batch?->number,
                    'topic' => $s->topic?->name,
                    'scheduled_start' => $s->scheduled_start?->toIso8601String(),
                    'scheduled_end' => $s->scheduled_end?->toIso8601String(),
                    'status' => $s->status->value,
                    'has_recording' => $s->recordings->isNotEmpty(),
                    'recording_id' => $s->recordings->first()?->id,
                    // Why the student can or cannot enter, resolved server-side so
                    // the portal never shows a Join button that would fail on click.
                    'join_opens_at' => $s->joinOpensAt()?->toIso8601String(),
                    'blocked_reason' => $this->blockedReason($s, $feeOk),
                    'can_join' => $this->blockedReason($s, $feeOk) === null,
                ])->all(),
            ]);
        });
    }

    /**
     * The single reason entry is refused, in the order the student should act
     * on it: clear your dues, wait for the window, then the room being ready.
     *
     * @param  array<int, bool>  $feeOk
     */
    private function blockedReason(LiveSession $session, array $feeOk): ?string
    {
        if ($session->batch !== null && ($feeOk[$session->batch->id] ?? true) === false) {
            return 'fees';
        }

        $opensAt = $session->joinOpensAt();

        if ($opensAt !== null && now()->lessThan($opensAt)) {
            return 'too_early';
        }

        if ($session->zoom_join_url === null) {
            return 'not_ready';
        }

        return null;
    }

    public function join(Request $request, int $session, JoinLiveSession $join): JsonResponse
    {
        return app(TenantContext::class)->run($request->user()->tenant, function () use ($request, $session, $join): JsonResponse {
            $model = LiveSession::query()->findOrFail($session);

            // JoinLiveSession throws ValidationException (→ 422) with the gate reason
            // (not enrolled / self-paced / fees / not ready) if entry isn't allowed.
            $url = $join->handle($model, $request->user());

            return response()->json(['data' => ['join_url' => $url]]);
        });
    }

    /** @return list<int> the student's occupying batch memberships */
    private function batchIds(int $userId): array
    {
        $occupying = array_map(fn (BatchMemberStatus $s) => $s->value, BatchMemberStatus::occupying());

        return BatchMember::query()
            ->where('user_id', $userId)
            ->whereIn('status', $occupying)
            ->pluck('batch_id')
            ->all();
    }
}
