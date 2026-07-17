<?php

declare(strict_types=1);

namespace App\Actions\Telemetry;

use App\Enums\ActivityType;
use App\Models\ActivityEvent;
use App\Models\User;
use App\Support\Points\PointsService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;

/**
 * Appends one event to the student telemetry stream (PRD §6.4). The single write
 * seam for every activity source (listeners on domain events + the client ingest).
 * DPDP telemetry-consent gating is deferred to the compliance milestone.
 */
final readonly class RecordActivity
{
    public function __construct(private PointsService $points) {}

    /**
     * @param  array<string, mixed>|null  $meta
     */
    public function handle(User $user, ActivityType $type, ?Model $subject = null, ?int $value = null, ?array $meta = null): ActivityEvent
    {
        $event = app(TenantContext::class)->run($user->tenant, fn (): ActivityEvent => ActivityEvent::query()->create([
            'tenant_id' => $user->tenant_id,
            'user_id' => $user->id,
            'type' => $type->value,
            'subject_type' => $subject !== null ? $subject::class : null,
            'subject_id' => $subject?->getKey(),
            'value' => $value,
            'meta' => $meta,
            'occurred_at' => now(),
        ]));

        // Streak points (P3.6): a day's streak state is settled by its first
        // event, so only the first write of the day pays for the evaluation.
        $isFirstToday = ActivityEvent::query()->withoutGlobalScopes()
            ->where('user_id', $user->id)
            ->where('occurred_at', '>=', now()->startOfDay())
            ->count() === 1;

        if ($isFirstToday && $user->tenant_id !== null) {
            $this->points->evaluateStreak($user, $user->tenant_id);
        }

        return $event;
    }
}
