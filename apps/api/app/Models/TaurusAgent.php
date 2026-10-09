<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TaurusAgentStatus;
use App\Models\Concerns\BelongsToTenant;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One bot on a Taurus floor (ADR 0052), upserted by (floor, slug) on ingest.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $taurus_workspace_id
 * @property string $floor
 * @property string $slug
 * @property string $name
 * @property string|null $zone
 * @property string|null $role
 * @property string|null $platform
 * @property TaurusAgentStatus $status
 * @property string|null $current_task
 * @property float $progress
 * @property array<string, mixed>|null $meta
 * @property Carbon|null $last_seen_at
 */
class TaurusAgent extends Model
{
    use BelongsToTenant;

    public const FLOORS = ['ops', 'recruitment'];

    protected $fillable = [
        'tenant_id',
        'taurus_workspace_id',
        'floor',
        'slug',
        'name',
        'zone',
        'role',
        'platform',
        'status',
        'current_task',
        'progress',
        'meta',
        'last_seen_at',
    ];

    protected $attributes = [
        'status' => 'idle',
        'progress' => 0,
    ];

    protected function casts(): array
    {
        return [
            'status' => TaurusAgentStatus::class,
            'progress' => 'float',
            'meta' => 'array',
            'last_seen_at' => 'datetime',
        ];
    }

    /** @return HasMany<TaurusTask, $this> */
    public function tasks(): HasMany
    {
        return $this->hasMany(TaurusTask::class, 'agent_id');
    }

    /** @return HasMany<TaurusEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(TaurusEvent::class, 'agent_id');
    }

    /** Silence past the threshold reads as offline, whatever was last reported. */
    public function effectiveStatus(?CarbonInterface $now = null): TaurusAgentStatus
    {
        $now ??= now();
        $threshold = (int) config('taurus.offline_after_minutes', 15);

        if ($this->last_seen_at === null || $this->last_seen_at->lt($now->copy()->subMinutes($threshold))) {
            return TaurusAgentStatus::Offline;
        }

        return $this->status;
    }

    /** @return BelongsTo<TaurusWorkspace, $this> */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(TaurusWorkspace::class, 'taurus_workspace_id');
    }
}
