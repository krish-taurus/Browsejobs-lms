<?php

declare(strict_types=1);

namespace App\Actions\Taurus;

use App\Enums\TaurusAgentStatus;
use App\Enums\TaurusTaskStatus;
use App\Models\TaurusAgent;
use App\Models\TaurusEvent;
use App\Models\TaurusSpendEvent;
use App\Models\TaurusTask;
use App\Models\TaurusWorkspace;
use App\Support\Taurus\TaurusClock;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * One snapshot of a Taurus floor for the console (ADR 0052): agents with
 * derived online/offline status, open + today's tasks, the latest feed, KPIs
 * and spend exactly as the bots reported it. Read-only; runs in the caller's
 * tenant via the global scope.
 */
final class BuildFloorState
{
    private const TASK_LIMIT = 100;

    private const EVENT_LIMIT = 40;

    private const SPEND_DAYS = 14;

    private int $workspaceId = 0;

    /**
     * @return array<string, mixed>
     */
    public function handle(TaurusWorkspace $workspace, string $floor): array
    {
        $this->workspaceId = $workspace->id;

        $now = now();
        $today = TaurusClock::startOfToday();

        $agents = TaurusAgent::query()->where('taurus_workspace_id', $this->workspaceId)->where('floor', $floor)->orderBy('name')->orderBy('id')->get();
        $names = $agents->pluck('name', 'id');

        $agentRows = $agents->map(fn (TaurusAgent $agent): array => [
            'id' => $agent->id,
            'slug' => $agent->slug,
            'name' => $agent->name,
            'zone' => $agent->zone,
            'role' => $agent->role,
            'platform' => $agent->platform,
            'status' => $agent->effectiveStatus($now)->value,
            'task' => $agent->current_task,
            'progress' => (float) $agent->progress,
            'last_seen_at' => $agent->last_seen_at?->toIso8601String(),
        ])->values();

        $tasks = $this->tasks($floor)
            ->where(function (Builder $q) use ($today): void {
                $q->whereIn('status', TaurusTaskStatus::open())
                    ->orWhere('finished_at', '>=', $today);
            })
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(self::TASK_LIMIT)
            ->get()
            ->map(fn (TaurusTask $task): array => self::task($task, $names->get($task->agent_id)))
            ->values();

        $events = TaurusEvent::query()
            ->where('taurus_workspace_id', $this->workspaceId)
            ->whereIn('agent_id', $this->agentIds($floor))
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->limit(self::EVENT_LIMIT)
            ->get()
            ->map(fn (TaurusEvent $event): array => [
                'id' => $event->id,
                'agent_id' => $event->agent_id,
                'agent_name' => $names->get($event->agent_id),
                'kind' => $event->kind,
                'status' => $event->status,
                'message' => $event->message,
                'occurred_at' => $event->occurred_at->toIso8601String(),
            ])
            ->values();

        $online = $agentRows->where('status', '!=', TaurusAgentStatus::Offline->value);

        return [
            'floor' => $floor,
            'generated_at' => $now->toIso8601String(),
            'agents' => $agentRows->all(),
            'tasks' => $tasks->all(),
            'events' => $events->all(),
            'kpis' => [
                'running' => $online->whereIn('status', [TaurusAgentStatus::Working->value, TaurusAgentStatus::Thinking->value])->count(),
                'needs' => $this->tasks($floor)->where('status', TaurusTaskStatus::NeedsApproval->value)->count(),
                'done_today' => $this->tasks($floor)->where('status', TaurusTaskStatus::Done->value)->where('finished_at', '>=', $today)->count(),
                'failed_today' => $this->tasks($floor)->where('status', TaurusTaskStatus::Failed->value)->where('finished_at', '>=', $today)->count(),
                'online' => $online->count(),
                'agents' => $agentRows->count(),
            ],
            'spend' => $this->spend($floor, $names),
        ];
    }

    /**
     * The task shape shared by the state feed and the approve/reject responses.
     *
     * @return array<string, mixed>
     */
    public static function task(TaurusTask $task, ?string $agentName): array
    {
        return [
            'id' => $task->id,
            'agent_id' => $task->agent_id,
            'agent_name' => $agentName,
            'ref' => $task->ref,
            'title' => $task->title,
            'status' => $task->status->value,
            'progress' => (float) $task->progress,
            'risk' => $task->risk,
            'approval_action' => $task->approval_action,
            'decision' => $task->decision,
            'created_at' => $task->created_at?->toIso8601String(),
            'updated_at' => $task->updated_at?->toIso8601String(),
            'finished_at' => $task->finished_at?->toIso8601String(),
        ];
    }

    /** @return Builder<TaurusTask> */
    private function tasks(string $floor): Builder
    {
        return TaurusTask::query()
            ->where('taurus_workspace_id', $this->workspaceId)
            ->whereIn('agent_id', $this->agentIds($floor));
    }

    /** @return Builder<TaurusAgent> */
    private function agentIds(string $floor): Builder
    {
        return TaurusAgent::query()->select('id')
            ->where('taurus_workspace_id', $this->workspaceId)
            ->where('floor', $floor);
    }

    /**
     * Spend grouped three ways, all as reported. `amount` is null for a group
     * whose rows carried only units — a missing price is never filled in.
     *
     * @param  Collection<int, string>  $names
     * @return array{today: list<array<string, mixed>>, by_agent: list<array<string, mixed>>, days: list<array<string, mixed>>}
     */
    private function spend(string $floor, Collection $names): array
    {
        $today = TaurusClock::startOfToday();
        $base = fn (): Builder => TaurusSpendEvent::query()
            ->where('taurus_workspace_id', $this->workspaceId)
            ->whereIn('agent_id', $this->agentIds($floor));

        $todayRows = $base()
            ->where('occurred_at', '>=', $today)
            ->selectRaw('source, currency, unit_label, SUM(amount_micros) as amount_micros, SUM(units) as units')
            ->groupBy('source', 'currency', 'unit_label')
            ->orderBy('source')
            ->get()
            ->map(fn ($row): array => [
                'source' => (string) $row->source,
                'currency' => $row->currency,
                'amount' => self::amount($row->getRawOriginal('amount_micros')),
                'units' => $row->getRawOriginal('units') !== null ? round((float) $row->getRawOriginal('units'), 4) : null,
                'unit_label' => $row->unit_label,
            ])
            ->values()
            ->all();

        $byAgent = $base()
            ->where('occurred_at', '>=', $today)
            ->whereNotNull('amount_micros')
            ->selectRaw('agent_id, currency, SUM(amount_micros) as amount_micros')
            ->groupBy('agent_id', 'currency')
            ->orderBy('agent_id')
            ->get()
            ->map(fn ($row): array => [
                'agent_id' => $row->agent_id,
                'agent_name' => $names->get($row->agent_id),
                'currency' => $row->currency,
                'amount' => self::amount($row->getRawOriginal('amount_micros')),
            ])
            ->values()
            ->all();

        return [
            'today' => $todayRows,
            'by_agent' => $byAgent,
            'days' => $this->days($base()),
        ];
    }

    /**
     * Daily totals per currency over the last 14 local days, zero-filled for
     * every currency that appears in the window so a chart has no gaps.
     *
     * @param  Builder<TaurusSpendEvent>  $query
     * @return list<array{date: string, currency: string|null, amount: float}>
     */
    private function days(Builder $query): array
    {
        $since = TaurusClock::startOfDaysAgo(self::SPEND_DAYS - 1);

        $rows = $query
            ->where('occurred_at', '>=', $since)
            ->whereNotNull('amount_micros')
            ->get(['occurred_at', 'currency', 'amount_micros']);

        if ($rows->isEmpty()) {
            return [];
        }

        $dates = [];
        for ($i = self::SPEND_DAYS - 1; $i >= 0; $i--) {
            $dates[] = TaurusClock::localDate(TaurusClock::startOfDaysAgo($i));
        }

        /** @var array<string, array<string, int>> $totals currency => date => micros */
        $totals = [];
        foreach ($rows as $row) {
            $currency = (string) ($row->currency ?? '');
            $date = TaurusClock::localDate($row->occurred_at);
            $totals[$currency][$date] = ($totals[$currency][$date] ?? 0) + (int) $row->amount_micros;
        }

        ksort($totals);

        $out = [];
        foreach ($totals as $currency => $byDate) {
            foreach ($dates as $date) {
                $out[] = [
                    'date' => $date,
                    'currency' => $currency !== '' ? $currency : null,
                    'amount' => ($byDate[$date] ?? 0) / 1_000_000,
                ];
            }
        }

        return $out;
    }

    private static function amount(mixed $micros): ?float
    {
        return $micros === null ? null : ((int) $micros) / 1_000_000;
    }
}
