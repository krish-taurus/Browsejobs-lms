<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A single touchpoint on a lead's contact timeline (PRD §6.12).
 *
 * @property int $id
 * @property int|null $tenant_id
 * @property int $lead_id
 * @property string $type
 * @property int|null $actor_id
 * @property string|null $body
 * @property array<string, mixed>|null $meta
 * @property Carbon $occurred_at
 */
class ContactTimelineEvent extends Model
{
    use BelongsToTenant;
    use HasFactory;

    public const TYPE_CREATED = 'created';

    public const TYPE_STAGE_CHANGED = 'stage_changed';

    public const TYPE_ASSIGNED = 'assigned';

    public const TYPE_NOTE = 'note';

    public const TYPE_TASK = 'task';

    public const TYPE_MASTERCLASS_REGISTERED = 'masterclass_registered';

    public const TYPE_MERGED = 'merged';

    public const TYPE_SLA_BREACHED = 'sla_breached';

    /** @var list<string> */
    protected $fillable = [
        'tenant_id', 'lead_id', 'type', 'actor_id', 'body', 'meta', 'occurred_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Lead, $this>
     */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
