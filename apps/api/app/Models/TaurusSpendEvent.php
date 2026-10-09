<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Spend exactly as a bot reported it (ADR 0052). `amount_micros` is the
 * reported amount × 1,000,000 in the reported currency, or null when the bot
 * reported only units — the console never estimates or converts a cost.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $taurus_workspace_id
 * @property int|null $agent_id
 * @property string $source
 * @property string|null $currency
 * @property int|null $amount_micros
 * @property float|null $units
 * @property string|null $unit_label
 * @property Carbon $occurred_at
 */
class TaurusSpendEvent extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'taurus_workspace_id',
        'agent_id',
        'source',
        'currency',
        'amount_micros',
        'units',
        'unit_label',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'amount_micros' => 'integer',
            'units' => 'float',
            'occurred_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<TaurusAgent, $this> */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(TaurusAgent::class, 'agent_id');
    }

    /** @return BelongsTo<TaurusWorkspace, $this> */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(TaurusWorkspace::class, 'taurus_workspace_id');
    }
}
