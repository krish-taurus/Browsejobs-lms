<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Something Taurus proposes to do in a client's pipeline, waiting for their
 * YES (agentic hiring). `params` holds the exact rows it will touch.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $employer_workspace_id
 * @property int|null $employer_job_id
 * @property int|null $user_id
 * @property int|null $employer_whatsapp_link_id
 * @property string $channel
 * @property string $kind
 * @property string|null $topic
 * @property array<string, mixed> $params
 * @property string $summary
 * @property string $status
 * @property string|null $result
 * @property int|null $decided_by
 * @property Carbon|null $decided_at
 * @property Carbon $expires_at
 * @property Carbon|null $notified_at
 * @property Carbon $created_at
 */
final class EmployerAgentAction extends Model
{
    use BelongsToTenant;

    public const PROPOSED = 'proposed';

    public const RUNNING = 'running';

    public const DONE = 'done';

    public const FAILED = 'failed';

    public const CANCELLED = 'cancelled';

    public const EXPIRED = 'expired';

    public const KIND_START_ROUND = 'start_round';

    public const KIND_MOVE_STAGE = 'move_stage';

    public const KIND_REJECT = 'reject';

    public const KIND_JOB_STATUS = 'job_status';

    public const KIND_RETAKE_ROUND = 'retake_round';

    /** A new role drafted in the web Ask bar, waiting for the client to approve it. No job exists until it is confirmed. */
    public const KIND_POST_JOB = 'post_job';

    /** Changes to a role that is already posted (experience, openings, location, skills, description). */
    public const KIND_UPDATE_JOB = 'update_job';

    public const CHANNEL_WHATSAPP = 'whatsapp';

    public const CHANNEL_WEB = 'web';

    public const CHANNEL_MILESTONE = 'milestone';

    protected $fillable = [
        'tenant_id', 'employer_workspace_id', 'employer_job_id', 'user_id', 'employer_whatsapp_link_id',
        'channel', 'kind', 'topic', 'params', 'summary', 'status', 'result', 'decided_by', 'decided_at', 'expires_at', 'notified_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'params' => 'array',
            'decided_at' => 'datetime',
            'expires_at' => 'datetime',
            'notified_at' => 'datetime',
        ];
    }

    /** @param  Builder<self>  $query */
    public function scopeOpen(Builder $query): void
    {
        $query->where('status', self::PROPOSED)->where('expires_at', '>', now());
    }

    public function isOpen(): bool
    {
        return $this->status === self::PROPOSED && $this->expires_at->isFuture();
    }

    /** @return BelongsTo<EmployerWorkspace, $this> */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(EmployerWorkspace::class, 'employer_workspace_id');
    }

    /** @return BelongsTo<EmployerJob, $this> */
    public function job(): BelongsTo
    {
        return $this->belongsTo(EmployerJob::class, 'employer_job_id');
    }

    /** @return array{id: int, kind: string, job_id: int|null, summary: string, status: string, result: string|null, channel: string, expires_at: string, created_at: string} */
    public function toApi(): array
    {
        return [
            'id' => $this->id,
            'kind' => $this->kind,
            'job_id' => $this->employer_job_id,
            'summary' => $this->summary,
            'status' => $this->status,
            'result' => $this->result,
            'channel' => $this->channel,
            'expires_at' => $this->expires_at->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
