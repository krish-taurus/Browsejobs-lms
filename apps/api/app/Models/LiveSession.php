<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\LiveSessionStatus;
use App\Models\Concerns\BelongsToTenant;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A scheduled live class backed by a Zoom meeting. `kind` separates normal
 * classes from weekly batch mentoring sessions (same engine, ADR 0043);
 * `host_user_id` is an explicit host override (the mentoring session's
 * mentor) — when null the module/lead trainer hosts.
 *
 * @property int $id
 * @property int|null $tenant_id
 * @property int $batch_id
 * @property int|null $topic_id
 * @property string $kind
 * @property int|null $host_user_id
 * @property string $title
 * @property Carbon $scheduled_start
 * @property Carbon|null $scheduled_end
 * @property string|null $zoom_meeting_id
 * @property string|null $zoom_join_url
 * @property int|null $zoom_license_id
 * @property LiveSessionStatus $status
 */
class LiveSession extends Model
{
    use BelongsToTenant;

    /** Minutes before the scheduled start a host may open the meeting. */
    public const HOST_LEAD_MINUTES = 15;

    /** Minutes after the scheduled end the host link stays valid. */
    public const HOST_TRAIL_MINUTES = 60;

    public const KIND_CLASS = 'class';

    public const KIND_MENTORING = 'mentoring';

    /** @var list<string> */
    protected $fillable = [
        'tenant_id', 'batch_id', 'topic_id', 'kind', 'host_user_id', 'title', 'scheduled_start', 'scheduled_end',
        'zoom_meeting_id', 'zoom_join_url', 'zoom_start_url', 'zoom_license_id', 'status', 'reminder_token', 'auto_record', 'wrapped_up_at',
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
     * Recordings land here via the Zoom `recording.completed` webhook, so an extra
     * ad-hoc session's recording shows up in its batch's library automatically.
     *
     * @return HasMany<Recording, $this>
     */
    public function recordings(): HasMany
    {
        return $this->hasMany(Recording::class);
    }

    /**
     * @return BelongsTo<Topic, $this>
     */
    public function topic(): BelongsTo
    {
        return $this->belongsTo(Topic::class);
    }

    /**
     * Explicit per-session host (the mentor of a mentoring session).
     *
     * @return BelongsTo<User, $this>
     */
    public function host(): BelongsTo
    {
        return $this->belongsTo(User::class, 'host_user_id');
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
     * True when a host may open the meeting now: from HOST_LEAD_MINUTES before the
     * start until HOST_TRAIL_MINUTES after the (planned) end.
     */
    public function hostWindowOpen(?CarbonInterface $now = null): bool
    {
        $now ??= now();
        $start = $this->scheduled_start;
        $end = $this->scheduled_end ?? $start->copy()->addMinutes(intdiv($this->plannedSeconds(), 60));

        return $now->gte($start->copy()->subMinutes(self::HOST_LEAD_MINUTES))
            && $now->lte($end->copy()->addMinutes(self::HOST_TRAIL_MINUTES));
    }

    /**
     * The class can be started at all: it is live/scheduled, its Zoom meeting exists,
     * and now is within the host window. (Says nothing about *who* may host.)
     */
    public function isHostable(): bool
    {
        return ! in_array($this->status, [LiveSessionStatus::Cancelled, LiveSessionStatus::Ended], true)
            && $this->zoom_start_url !== null
            && $this->hostWindowOpen();
    }

    /**
     * Whether this staff member may host now: the class is hostable and they are its
     * assigned host — the explicit per-session host when one is set (a mentoring
     * session's mentor), else the module trainer falling back to the lead — or an admin.
     */
    public function hostableBy(User $user): bool
    {
        if (! $this->isHostable()) {
            return false;
        }

        if ($user->hasRole('admin', 'super-admin')) {
            return true;
        }

        if ($this->host_user_id !== null) {
            return (int) $this->host_user_id === (int) $user->id;
        }

        $this->batch->loadMissing(['trainer', 'moduleTrainers.trainer']);
        $teacher = $this->batch->trainerForModule($this->topic?->module_id);

        return $teacher !== null && (int) $teacher->id === (int) $user->id;
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
            'auto_record' => 'boolean',
            'wrapped_up_at' => 'datetime',
        ];
    }

    /**
     * When the Join button opens: a short window before the class starts, so
     * students cannot sit in an empty room hours early. Null when the session
     * has no scheduled start.
     */
    public function joinOpensAt(): ?\Illuminate\Support\Carbon
    {
        if ($this->scheduled_start === null) {
            return null;
        }

        return $this->scheduled_start
            ->copy()
            ->subMinutes((int) config('classes.join_opens_minutes_before', 1));
    }
}
