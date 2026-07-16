<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TicketCategory;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Admin-configurable routing + SLA for one ticket category (PRD §6.13). `routing`
 * selects the assignment strategy against the linked SupportTeam / batch trainer.
 *
 * @property int $id
 * @property int|null $tenant_id
 * @property TicketCategory $category
 * @property string $label
 * @property string $routing
 * @property int|null $support_team_id
 * @property int $first_response_minutes
 * @property int $resolution_minutes
 * @property int|null $round_robin_pointer
 * @property bool $active
 */
class TicketRoute extends Model
{
    use BelongsToTenant;
    use HasFactory;

    public const ROUTING_TEAM = 'team';

    public const ROUTING_BATCH_TRAINER = 'batch_trainer';

    public const ROUTING_ADMIN = 'admin';

    /** @var list<string> */
    protected $fillable = [
        'tenant_id', 'category', 'label', 'routing', 'support_team_id',
        'first_response_minutes', 'resolution_minutes', 'round_robin_pointer', 'active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => TicketCategory::class,
            'first_response_minutes' => 'integer',
            'resolution_minutes' => 'integer',
            'active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<SupportTeam, $this>
     */
    public function supportTeam(): BelongsTo
    {
        return $this->belongsTo(SupportTeam::class);
    }
}
