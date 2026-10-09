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
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Applies a batch of bot reports to the Taurus floor (ADR 0052).
 *
 * Idempotent by design: agents upsert by (floor, slug), tasks by (agent, ref),
 * and feed lines are only written when something actually changed — so a bot
 * that re-sends the same report (a retry, a heartbeat) does not flood the feed.
 * Spend is stored exactly as reported; nothing here estimates a cost.
 *
 * Everything lands in the one workspace the bot's token belongs to; the
 * `taurus.ingest` middleware resolved it (and its tenant) from the token.
 */
final class IngestEvents
{
    /**
     * @param  array{floor?: string, events: list<array<string, mixed>>}  $payload
     * @return int events accepted
     */
    public function handle(TaurusWorkspace $workspace, array $payload): int
    {
        $floor = (string) ($payload['floor'] ?? 'ops');

        DB::transaction(function () use ($workspace, $payload, $floor): void {
            foreach ($payload['events'] as $event) {
                $this->apply($workspace, $floor, $event);
            }
        });

        return count($payload['events']);
    }

    /**
     * @param  array<string, mixed>  $event
     */
    private function apply(TaurusWorkspace $workspace, string $floor, array $event): void
    {
        $now = now();
        // Stored timestamps are in the app timezone; a bot may send any offset.
        $occurredAt = ! empty($event['occurred_at'])
            ? Carbon::parse((string) $event['occurred_at'])->setTimezone((string) config('app.timezone'))
            : $now;

        $agent = $this->upsertAgent($workspace, $floor, (array) $event['agent'], $now);

        if (! empty($event['status'])) {
            $status = TaurusAgentStatus::from((string) $event['status']);
            $isNew = ! $agent->exists;
            $changed = $isNew || $agent->status !== $status;
            $agent->status = $status;
            $agent->save();

            if ($changed) {
                $this->feed($agent, null, TaurusEvent::KIND_STATUS, $status->value, $status->sentence(), $occurredAt);
            }
        } else {
            $agent->save();
        }

        $task = null;
        if (is_array($event['task'] ?? null)) {
            $task = $this->applyTask($agent, $event['task'], $occurredAt);
        }

        if (! empty($event['message'])) {
            $this->feed($agent, $task, TaurusEvent::KIND_MESSAGE, null, (string) $event['message'], $occurredAt);
        }

        if (is_array($event['spend'] ?? null)) {
            $this->recordSpend($agent, $task, $event['spend'], $occurredAt);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function upsertAgent(TaurusWorkspace $workspace, string $floor, array $data, CarbonInterface $now): TaurusAgent
    {
        $slug = (string) $data['slug'];

        $agent = TaurusAgent::query()->firstOrNew([
            'taurus_workspace_id' => $workspace->id,
            'floor' => $floor,
            'slug' => $slug,
        ]);

        $name = trim((string) ($data['name'] ?? ''));
        if ($name !== '') {
            $agent->name = $name;
        } elseif (! $agent->exists) {
            $agent->name = $slug;
        }

        foreach (['zone', 'role', 'platform'] as $field) {
            if (isset($data[$field]) && trim((string) $data[$field]) !== '') {
                $agent->{$field} = trim((string) $data[$field]);
            }
        }

        $agent->last_seen_at = $now;

        return $agent;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function applyTask(TaurusAgent $agent, array $data, CarbonInterface $occurredAt): TaurusTask
    {
        /** @var TaurusTask $task */
        $task = $agent->tasks()->firstOrNew(['ref' => (string) $data['ref']]);
        $task->taurus_workspace_id = $agent->taurus_workspace_id;
        $previous = $task->exists ? $task->status : null;
        $previousTitle = $task->exists ? $task->title : null;

        $title = trim((string) ($data['title'] ?? ''));
        $task->title = $title !== '' ? $title : ($task->exists ? $task->title : (string) $data['ref']);

        $approval = is_array($data['approval'] ?? null) ? $data['approval'] : null;
        if ($approval !== null) {
            $task->approval_action = (string) $approval['action'];
            $task->risk = isset($approval['risk']) ? (string) $approval['risk'] : $task->risk;
        }

        if (isset($data['progress'])) {
            $task->progress = max(0.0, min(1.0, (float) $data['progress']));
        }

        // An approval block without a status is a request for approval.
        $reported = ! empty($data['status'])
            ? TaurusTaskStatus::from((string) $data['status'])
            : ($approval !== null ? TaurusTaskStatus::NeedsApproval : null);

        // A human rejection is final: the bot cannot talk its way past it.
        if ($previous === TaurusTaskStatus::Rejected) {
            $reported = null;
        }

        if (! $task->exists && $reported === null) {
            $reported = TaurusTaskStatus::Queued;
        }

        $changed = $reported !== null && $reported !== $previous;

        if ($changed) {
            $task->status = $reported;

            if ($reported === TaurusTaskStatus::Running && $task->started_at === null) {
                $task->started_at = $occurredAt;
            }

            if ($reported === TaurusTaskStatus::Done || $reported === TaurusTaskStatus::Failed) {
                $task->finished_at = $occurredAt;
            }

            if ($reported === TaurusTaskStatus::Done && ! isset($data['progress'])) {
                $task->progress = 1.0;
            }

            if (! $reported->isFinished()) {
                $task->finished_at = null;
            }

            // A fresh approval request needs a fresh decision.
            if ($reported === TaurusTaskStatus::NeedsApproval) {
                $task->decision = null;
                $task->decision_note = null;
                $task->decided_by = null;
                $task->decided_at = null;
            }
        }

        $task->save();

        if ($changed) {
            $this->feed($agent, $task, TaurusEvent::KIND_TASK, $task->status->value, $this->taskSentence($task), $occurredAt);
        }

        $this->syncAgentWithTask($agent, $task, $previousTitle);

        return $task;
    }

    private function syncAgentWithTask(TaurusAgent $agent, TaurusTask $task, ?string $previousTitle): void
    {
        $status = $task->status;

        if ($status === TaurusTaskStatus::Running || $status === TaurusTaskStatus::NeedsApproval) {
            $agent->current_task = Str::limit($task->title, 237);
            $agent->progress = $task->progress;

            if ($status === TaurusTaskStatus::NeedsApproval) {
                $agent->status = TaurusAgentStatus::Needs;
            }
        } elseif ($status->isFinished() && in_array($agent->current_task, [$task->title, $previousTitle], true)) {
            // The agent's headline task just ended; don't keep advertising it.
            $agent->current_task = null;
            $agent->progress = 0;
        }

        $agent->save();
    }

    private function taskSentence(TaurusTask $task): string
    {
        return match ($task->status) {
            TaurusTaskStatus::Queued => "queued: {$task->title}",
            TaurusTaskStatus::Running => "started: {$task->title}",
            TaurusTaskStatus::NeedsApproval => 'needs your approval: '.($task->approval_action ?: $task->title),
            TaurusTaskStatus::Done => "completed: {$task->title}",
            TaurusTaskStatus::Failed => "failed: {$task->title}",
            TaurusTaskStatus::Rejected => "rejected: {$task->title}",
        };
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function recordSpend(TaurusAgent $agent, ?TaurusTask $task, array $data, CarbonInterface $occurredAt): void
    {
        $amount = $data['amount'] ?? null;
        $currency = isset($data['currency']) && $data['currency'] !== '' ? strtoupper((string) $data['currency']) : null;
        $units = isset($data['units']) ? (float) $data['units'] : null;
        $unitLabel = isset($data['unit_label']) && trim((string) $data['unit_label']) !== '' ? trim((string) $data['unit_label']) : null;
        $source = trim((string) $data['source']);

        TaurusSpendEvent::query()->create([
            'taurus_workspace_id' => $agent->taurus_workspace_id,
            'agent_id' => $agent->id,
            'source' => $source,
            'currency' => $currency,
            'amount_micros' => $amount !== null ? self::toMicros($amount) : null,
            'units' => $units,
            'unit_label' => $unitLabel,
            'occurred_at' => $occurredAt,
        ]);

        $parts = [];
        if ($amount !== null) {
            $parts[] = trim(self::plainNumber($amount).' '.($currency ?? ''));
        }
        if ($units !== null) {
            $parts[] = trim(self::plainNumber($units).' '.($unitLabel ?? 'units'));
        }

        $message = "reported spend on {$source}".($parts !== [] ? ': '.implode(' · ', $parts) : '');

        $this->feed($agent, $task, TaurusEvent::KIND_SPEND, null, $message, $occurredAt);
    }

    private function feed(TaurusAgent $agent, ?TaurusTask $task, string $kind, ?string $status, string $message, CarbonInterface $occurredAt): void
    {
        TaurusEvent::query()->create([
            'taurus_workspace_id' => $agent->taurus_workspace_id,
            'agent_id' => $agent->id,
            'task_id' => $task?->id,
            'kind' => $kind,
            'status' => $status,
            'message' => Str::limit($message, 497),
            'occurred_at' => $occurredAt,
        ]);
    }

    /**
     * The reported amount × 1,000,000 as an integer, computed on the decimal
     * string where possible so "0.1" is exactly 100000 micros — no float drift.
     */
    public static function toMicros(int|float|string $amount): int
    {
        $string = is_string($amount) && preg_match('/^\d+(\.\d+)?$/', trim($amount)) === 1
            ? trim($amount)
            : number_format((float) $amount, 7, '.', '');

        [$whole, $fraction] = array_pad(explode('.', $string, 2), 2, '');
        $fraction = str_pad($fraction, 7, '0');

        $micros = (int) $whole * 1_000_000 + (int) substr($fraction, 0, 6);

        // Round half up on the seventh decimal.
        if ((int) $fraction[6] >= 5) {
            $micros++;
        }

        return $micros;
    }

    /** "1.250000" → "1.25", "300.0" → "300" — the number as the bot meant it. */
    private static function plainNumber(int|float|string $value): string
    {
        $string = is_string($value) ? trim($value) : number_format((float) $value, 6, '.', '');

        return str_contains($string, '.') ? rtrim(rtrim($string, '0'), '.') : $string;
    }
}
