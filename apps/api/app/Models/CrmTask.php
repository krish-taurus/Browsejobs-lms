<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A counselor task in the CRM queue (PRD §6.12). Created manually or by an SLA
 * breach. `completed_at` null == open.
 *
 * @property int $id
 * @property int|null $tenant_id
 * @property int $lead_id
 * @property int $assigned_to
 * @property string $title
 * @property Carbon|null $due_at
 * @property Carbon|null $completed_at
 * @property string $source
 */
class CrmTask extends Model
{
    use BelongsToTenant;
    use HasFactory;

    public const SOURCE_MANUAL = 'manual';

    public const SOURCE_SLA = 'sla';

    public const SOURCE_CONVERSION = 'conversion';

    public const SOURCE_QUIZ = 'quiz';

    /** @var list<string> */
    protected $fillable = [
        'tenant_id', 'lead_id', 'assigned_to', 'title', 'due_at', 'completed_at', 'source',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'due_at' => 'datetime',
            'completed_at' => 'datetime',
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
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
}
