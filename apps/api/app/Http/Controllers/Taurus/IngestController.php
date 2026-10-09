<?php

declare(strict_types=1);

namespace App\Http\Controllers\Taurus;

use App\Actions\Taurus\IngestEvents;
use App\Http\Controllers\Controller;
use App\Http\Requests\Taurus\DecisionsRequest;
use App\Http\Requests\Taurus\IngestEventsRequest;
use App\Models\TaurusTask;
use App\Models\TaurusWorkspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * The bots' side of Taurus (ADR 0052): they push reports in and poll for the
 * human decisions on tasks they parked. The bearer token identifies the
 * workspace (`taurus.ingest` middleware); nothing here can reach another one.
 */
final class IngestController extends Controller
{
    private const DECISION_LIMIT = 200;

    public function store(IngestEventsRequest $request, IngestEvents $ingest): JsonResponse
    {
        /** @var array{floor?: string, events: list<array<string, mixed>>} $payload */
        $payload = $request->validated();

        return response()->json(['data' => ['accepted' => $ingest->handle(self::workspace($request), $payload)]], 202);
    }

    /**
     * Decisions made strictly after `since` (default: the last 24 hours),
     * oldest first, so a bot can page forward by passing the last decided_at.
     */
    public function decisions(DecisionsRequest $request): JsonResponse
    {
        $since = $request->validated('since') !== null
            ? Carbon::parse((string) $request->validated('since'))->setTimezone((string) config('app.timezone'))
            : now()->subDay();

        $rows = TaurusTask::query()
            ->where('taurus_workspace_id', self::workspace($request)->id)
            ->with('agent:id,slug,floor')
            ->whereNotNull('decision')
            ->whereNotNull('decided_at')
            ->where('decided_at', '>', $since)
            ->orderBy('decided_at')
            ->orderBy('id')
            ->limit(self::DECISION_LIMIT)
            ->get()
            ->map(fn (TaurusTask $task): array => [
                'task_ref' => $task->ref,
                'agent_slug' => $task->agent?->slug,
                'floor' => $task->agent?->floor,
                'decision' => $task->decision,
                'note' => $task->decision_note,
                'decided_at' => $task->decided_at?->toIso8601String(),
            ])
            ->values();

        return response()->json(['data' => $rows]);
    }

    /** The workspace the bearer token belongs to, set by the `taurus.ingest` middleware. */
    private static function workspace(Request $request): TaurusWorkspace
    {
        /** @var TaurusWorkspace $workspace */
        $workspace = $request->attributes->get('taurus_workspace');

        return $workspace;
    }
}
