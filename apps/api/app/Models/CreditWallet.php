<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * A student's credit balance for one metered feature (PRD §6.17).
 *
 * @property int $id
 * @property int|null $tenant_id
 * @property int $user_id
 * @property string $feature
 * @property int $balance
 */
class CreditWallet extends Model
{
    use BelongsToTenant;

    /** @var list<string> */
    protected $fillable = ['tenant_id', 'user_id', 'feature', 'balance'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['balance' => 'integer'];
    }
}
