<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A single bootcamp-conversion nudge for a fee plan rung (PRD §5 Stage 3). The
 * unique (fee_plan_id, rung) index dedupes the daily nudge run.
 *
 * @property int $id
 * @property int|null $tenant_id
 * @property int $fee_plan_id
 * @property string $rung
 * @property string $channel
 * @property Carbon $sent_at
 */
class ConversionNudge extends Model
{
    use BelongsToTenant;
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'tenant_id', 'fee_plan_id', 'rung', 'channel', 'sent_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<FeePlan, $this>
     */
    public function feePlan(): BelongsTo
    {
        return $this->belongsTo(FeePlan::class);
    }
}
