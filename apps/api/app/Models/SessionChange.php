<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * An audit-style log of a live session being cancelled or rescheduled
 * (PRD §6.3), with actor and reason.
 *
 * @property int $id
 * @property int|null $tenant_id
 * @property int $live_session_id
 * @property int|null $actor_id
 * @property string $type
 * @property string|null $reason
 * @property Carbon|null $old_start
 * @property Carbon|null $new_start
 */
class SessionChange extends Model
{
    use BelongsToTenant;

    /** @var list<string> */
    protected $fillable = [
        'tenant_id', 'live_session_id', 'actor_id', 'type', 'reason', 'old_start', 'new_start',
    ];

    /**
     * @return BelongsTo<LiveSession, $this>
     */
    public function liveSession(): BelongsTo
    {
        return $this->belongsTo(LiveSession::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'old_start' => 'datetime',
            'new_start' => 'datetime',
        ];
    }
}
