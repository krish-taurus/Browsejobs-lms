<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * Period-scoped usage of an included quota (PRD §6.17).
 *
 * @property int $id
 * @property int|null $tenant_id
 * @property int $user_id
 * @property string $feature
 * @property string $period_key
 * @property int $used
 * @property int|null $limit_amount
 */
class QuotaUsage extends Model
{
    use BelongsToTenant;

    /** @var list<string> */
    protected $fillable = ['tenant_id', 'user_id', 'feature', 'period_key', 'used', 'limit_amount'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['used' => 'integer', 'limit_amount' => 'integer'];
    }
}
