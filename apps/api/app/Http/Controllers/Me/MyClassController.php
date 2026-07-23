<?php

declare(strict_types=1);

namespace App\Http\Controllers\Me;

use App\Actions\LiveClasses\JoinLiveSession;
use App\Enums\BatchMemberStatus;
use App\Http\Controllers\Controller;
use App\Models\BatchMember;
use App\Models\LiveSession;
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

            $sessions = LiveSession::query()
                ->whereIn('batch_id', $batchIds)
                ->with(['batch:id,number', 'topic:id,name', 'recordings' => fn ($q) => $q->where('status', 'stored')])
                ->orderByDesc('scheduled_start')
                ->get();

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
                ])->all(),
            ]);
        });
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
