<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One plain-English line in the Taurus floor feed (ADR 0052).
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $taurus_workspace_id
 * @property int $agent_id
 * @property int|null $task_id
 * @property string $kind status | task | message | spend | approval
 * @property string|null $status
 * @property string $message
 * @property Carbon $occurred_at
 */
class TaurusEvent extends Model
{
    use BelongsToTenant;

    public const KIND_STATUS = 'status';

    public const KIND_TASK = 'task';

    public const KIND_MESSAGE = 'message';

    public const KIND_SPEND = 'spend';

    public const KIND_APPROVAL = 'approval';

    protected $fillable = [
        'tenant_id',
        'taurus_workspace_id',
        'agent_id',
        'task_id',
        'kind',
        'status',
        'message',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<TaurusAgent, $this> */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(TaurusAgent::class, 'agent_id');
    }

    /** @return BelongsTo<TaurusTask, $this> */
    public function task(): BelongsTo
    {
        return $this->belongsTo(TaurusTask::class, 'task_id');
    }

    /** @return BelongsTo<TaurusWorkspace, $this> */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(TaurusWorkspace::class, 'taurus_workspace_id');
    }
}
