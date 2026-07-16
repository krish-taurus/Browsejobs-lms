<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * One entry in the credit-wallet ledger (PRD §6.17). `delta` signed.
 *
 * @property int $id
 * @property int|null $tenant_id
 * @property int $user_id
 * @property string $feature
 * @property int $delta
 * @property int $balance_after
 * @property string $reason
 */
class CreditTransaction extends Model
{
    use BelongsToTenant;

    /** @var list<string> */
    protected $fillable = [
        'tenant_id', 'user_id', 'feature', 'delta', 'balance_after', 'reason', 'source_type', 'source_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['delta' => 'integer', 'balance_after' => 'integer'];
    }
}
