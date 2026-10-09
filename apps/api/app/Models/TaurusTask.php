<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TaurusTaskStatus;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A unit of work a Taurus bot reported, keyed by the bot's own ref. A task in
 * `needs_approval` waits for a human decision on the console (ADR 0052).
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $taurus_workspace_id
 * @property int $agent_id
 * @property string $ref
 * @property string $title
 * @property TaurusTaskStatus $status
 * @property float $progress
 * @property string|null $risk
 * @property string|null $approval_action
 * @property string|null $decision
 * @property string|null $decision_note
 * @property int|null $decided_by
 * @property Carbon|null $decided_at
 * @property Carbon|null $started_at
 * @property Carbon|null $finished_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class TaurusTask extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'taurus_workspace_id',
        'agent_id',
        'ref',
        'title',
        'status',
        'progress',
        'risk',
        'approval_action',
        'decision',
        'decision_note',
        'decided_by',
        'decided_at',
        'started_at',
        'finished_at',
    ];

    protected $attributes = [
        'status' => 'queued',
        'progress' => 0,
    ];

    protected function casts(): array
    {
        return [
            'status' => TaurusTaskStatus::class,
            'progress' => 'float',
            'decided_at' => 'datetime',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<TaurusAgent, $this> */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(TaurusAgent::class, 'agent_id');
    }

    /** @return BelongsTo<User, $this> */
    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    /** @return BelongsTo<TaurusWorkspace, $this> */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(TaurusWorkspace::class, 'taurus_workspace_id');
    }
}
