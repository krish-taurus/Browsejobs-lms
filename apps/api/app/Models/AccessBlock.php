<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AccessBlockLevel;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A fee-driven access block on a student (PRD §6.8). `lifted_at` null == active.
 *
 * @property int $id
 * @property int|null $tenant_id
 * @property int $user_id
 * @property int|null $batch_id
 * @property int|null $fee_plan_id
 * @property AccessBlockLevel $level
 * @property string|null $reason
 * @property Carbon $blocked_at
 * @property Carbon|null $lifted_at
 * @property string|null $lifted_reason
 */
class AccessBlock extends Model
{
    use BelongsToTenant;
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'tenant_id', 'user_id', 'batch_id', 'fee_plan_id', 'level',
        'reason', 'blocked_at', 'lifted_at', 'lifted_reason',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'level' => AccessBlockLevel::class,
            'blocked_at' => 'datetime',
            'lifted_at' => 'datetime',
        ];
    }

    public function isActive(): bool
    {
        return $this->lifted_at === null;
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
