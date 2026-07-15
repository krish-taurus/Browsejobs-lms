<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A student's attendance for one live session, accumulated across Zoom
 * join/leave webhooks.
 *
 * @property int $id
 * @property int|null $tenant_id
 * @property int $live_session_id
 * @property int $user_id
 * @property Carbon|null $first_joined_at
 * @property Carbon|null $last_left_at
 * @property Carbon|null $open_join_at
 * @property int $total_seconds
 * @property int $attended_pct
 * @property bool $is_late
 * @property int|null $override_by
 * @property string|null $override_reason
 */
class Attendance extends Model
{
    use BelongsToTenant;

    protected $table = 'attendance';

    /** @var list<string> */
    protected $fillable = [
        'tenant_id', 'live_session_id', 'user_id', 'first_joined_at', 'last_left_at',
        'open_join_at', 'total_seconds', 'attended_pct', 'is_late', 'override_by', 'override_reason',
    ];

    /**
     * @return BelongsTo<LiveSession, $this>
     */
    public function liveSession(): BelongsTo
    {
        return $this->belongsTo(LiveSession::class);
    }

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
        return [
            'first_joined_at' => 'datetime',
            'last_left_at' => 'datetime',
            'open_join_at' => 'datetime',
            'total_seconds' => 'integer',
            'attended_pct' => 'integer',
            'is_late' => 'boolean',
        ];
    }
}
