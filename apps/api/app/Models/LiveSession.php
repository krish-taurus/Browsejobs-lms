<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\LiveSessionStatus;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A scheduled live class backed by a Zoom meeting.
 *
 * @property int $id
 * @property int|null $tenant_id
 * @property int $batch_id
 * @property int|null $topic_id
 * @property string $title
 * @property Carbon $scheduled_start
 * @property Carbon|null $scheduled_end
 * @property string|null $zoom_meeting_id
 * @property string|null $zoom_join_url
 * @property LiveSessionStatus $status
 */
class LiveSession extends Model
{
    use BelongsToTenant;

    /** @var list<string> */
    protected $fillable = [
        'tenant_id', 'batch_id', 'topic_id', 'title', 'scheduled_start', 'scheduled_end',
        'zoom_meeting_id', 'zoom_join_url', 'zoom_start_url', 'status',
    ];

    /**
     * @return BelongsTo<Batch, $this>
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    /**
     * @return HasMany<Attendance, $this>
     */
    public function attendance(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    /**
     * Planned duration in seconds; defaults to 60 minutes when no end is set.
     */
    public function plannedSeconds(): int
    {
        if ($this->scheduled_end === null) {
            return 3600;
        }

        return max(1, (int) $this->scheduled_start->diffInSeconds($this->scheduled_end));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scheduled_start' => 'datetime',
            'scheduled_end' => 'datetime',
            'status' => LiveSessionStatus::class,
        ];
    }
}
