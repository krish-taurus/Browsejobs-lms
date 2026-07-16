<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EntitlementStatus;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A non-countable access grant (PRD §6.17): self_paced/live batch access or
 * career_plus. `expires_at` null = perpetual.
 *
 * @property int $id
 * @property int|null $tenant_id
 * @property int $user_id
 * @property string $key
 * @property string $kind
 * @property EntitlementStatus $status
 * @property int|null $source_purchase_id
 * @property Carbon|null $granted_at
 * @property Carbon|null $expires_at
 */
class Entitlement extends Model
{
    use BelongsToTenant;

    /** @var list<string> */
    protected $fillable = [
        'tenant_id', 'user_id', 'key', 'kind', 'status', 'source_purchase_id', 'granted_at', 'expires_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => EntitlementStatus::class,
            'granted_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    /** Active and not past expiry. */
    public function isLive(): bool
    {
        return $this->status === EntitlementStatus::Active
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }
}
