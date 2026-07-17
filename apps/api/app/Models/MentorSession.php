<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One booked 1:1 (PRD §6.11) — mentoring or a placement interview, same
 * engine. Mentor feedback_score feeds PRI; student_rating feeds the mentor's
 * public rating.
 *
 * @property int $id
 * @property int|null $tenant_id
 * @property int $mentor_profile_id
 * @property int $student_id
 * @property string $purpose
 * @property string $status
 * @property string|null $no_show_side
 * @property Carbon $starts_at
 * @property int $duration_minutes
 * @property string|null $zoom_meeting_id
 * @property string|null $join_url
 * @property string|null $start_url
 * @property array<string, mixed>|null $feedback
 * @property int|null $feedback_score
 * @property int|null $student_rating
 * @property string|null $student_comment
 * @property Carbon|null $reminded_24h_at
 * @property Carbon|null $reminded_1h_at
 */
class MentorSession extends Model
{
    use BelongsToTenant;
    use HasFactory;

    public const STATUS_BOOKED = 'booked';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_NO_SHOW = 'no_show';

    public const PURPOSE_MENTORING = 'mentoring';

    public const PURPOSE_PLACEMENT = 'placement_interview';

    /** @var list<string> */
    protected $fillable = [
        'tenant_id', 'mentor_profile_id', 'student_id', 'purpose', 'status',
        'no_show_side', 'starts_at', 'duration_minutes', 'zoom_meeting_id',
        'join_url', 'start_url', 'feedback', 'feedback_score', 'student_rating',
        'student_comment', 'cancelled_by', 'cancelled_at', 'reminded_24h_at', 'reminded_1h_at',
    ];

    /**
     * @return BelongsTo<MentorProfile, $this>
     */
    public function mentor(): BelongsTo
    {
        return $this->belongsTo(MentorProfile::class, 'mentor_profile_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    /** The 4-hour notice rule (PRD §6.11) for student-initiated changes. */
    public function withinNoticeWindow(): bool
    {
        return now()->addHours((int) config('mentoring.cancel_notice_hours', 4))->lt($this->starts_at);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'feedback' => 'array',
            'cancelled_at' => 'datetime',
            'reminded_24h_at' => 'datetime',
            'reminded_1h_at' => 'datetime',
        ];
    }
}
