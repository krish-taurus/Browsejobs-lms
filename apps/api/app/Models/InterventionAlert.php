<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A rage-signal intervention alert (PRD §6.20): churn precursors detected →
 * a human steps in BEFORE the frustration goes public.
 *
 * @property int $id
 * @property int|null $tenant_id
 * @property int $user_id
 * @property list<string> $signals
 * @property string $status
 * @property int|null $handled_by
 * @property string|null $resolution
 */
class InterventionAlert extends Model
{
    use BelongsToTenant;

    public const STATUS_OPEN = 'open';

    public const STATUS_HANDLED = 'handled';

    /** @var list<string> */
    protected $fillable = [
        'tenant_id', 'user_id', 'signals', 'status', 'handled_by', 'handled_at', 'resolution',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['signals' => 'array', 'handled_at' => 'datetime'];
    }
}
