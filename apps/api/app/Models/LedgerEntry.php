<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\LedgerDirection;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A single per-student fee ledger entry (PRD §6.8). Debit = amount owed
 * (per instalment at plan creation); credit = payment captured.
 *
 * @property int $id
 * @property int|null $tenant_id
 * @property int $user_id
 * @property int $fee_plan_id
 * @property int|null $payment_id
 * @property LedgerDirection $direction
 * @property int $amount_paise
 * @property string $description
 * @property Carbon $occurred_at
 */
class LedgerEntry extends Model
{
    use BelongsToTenant;
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'tenant_id', 'user_id', 'fee_plan_id', 'payment_id',
        'direction', 'amount_paise', 'description', 'occurred_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'direction' => LedgerDirection::class,
            'amount_paise' => 'integer',
            'occurred_at' => 'datetime',
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
