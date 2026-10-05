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

    /** A two-round hiring interview the student applies for and staff approve. */
    public const PURPOSE_INTERVIEW = 'interview_round';

    /** Applied for, nobody has acted yet. */
    public const APPROVAL_PENDING = 'pending';

    public const APPROVAL_APPROVED = 'approved';

    public const APPROVAL_DECLINED = 'declined';

    /** Round 1's verdict — `cleared` is what unlocks round 2. */
    public const OUTCOME_CLEARED = 'cleared';

    public const OUTCOME_NOT_CLEARED = 'not_cleared';

    /** @var list<string> */
    protected $fillable = [
        'tenant_id', 'mentor_profile_id', 'student_id', 'purpose', 'status',
        'no_show_side', 'starts_at', 'duration_minutes', 'zoom_meeting_id',
        'join_url', 'start_url', 'feedback', 'feedback_score', 'student_rating',
        'student_comment', 'cancelled_by', 'cancelled_at', 'reminded_24h_at', 'reminded_1h_at',
        'interview_round', 'approval_status', 'outcome', 'reviewed_by', 'reviewed_at',
        'review_note', 'requested_starts_at',
    ];

    /** Is this a two-round hiring interview rather than ordinary mentoring? */
    public function isInterview(): bool
    {
        return $this->interview_round !== null;
    }

    /** Waiting on a Tech Mentor or Tech Manager to approve or move it. */
    public function isAwaitingReview(): bool
    {
        return $this->approval_status === self::APPROVAL_PENDING;
    }

    /**
     * Round 2 is not a free choice — it opens only once round 1 came back
     * cleared. Checked here so the API, the commands and the tests all agree
     * on one definition.
     */
    public static function hasClearedRoundOne(int $studentId): bool
    {
        return self::query()
            ->where('student_id', $studentId)
            ->where('interview_round', 1)
            ->where('outcome', self::OUTCOME_CLEARED)
            ->exists();
    }

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

    /** When this booking is due to finish. */
    public function endsAt(): ?Carbon
    {
        return $this->starts_at?->copy()->addMinutes($this->duration_minutes ?: 45);
    }

    /**
     * Is this interview still joinable?
     *
     * The door opens a minute before the slot and shuts a grace period after
     * it ends. Anything already decided is shut regardless — a verdict means
     * the interview happened.
     */
    public function canStillJoin(): bool
    {
        $ends = $this->endsAt();

        if ($ends === null || $this->outcome !== null) {
            return false;
        }

        return now()->lt($ends->copy()->addMinutes((int) config('interviews.join_grace_minutes', 30)));
    }

    /**
     * The slot came and went and nobody recorded a verdict.
     *
     * This is what stops a forgotten booking from holding a student's round
     * open forever: once it lapses they may apply again, and the portal stops
     * calling it confirmed. Declined and decided sessions are not lapsed —
     * they already have an answer.
     */
    public function hasLapsed(): bool
    {
        $ends = $this->endsAt();

        if ($ends === null || $this->outcome !== null) {
            return false;
        }

        if (! in_array($this->approval_status, [self::APPROVAL_PENDING, self::APPROVAL_APPROVED], true)) {
            return false;
        }

        return now()->gte($ends->copy()->addHours((int) config('interviews.lapse_after_hours', 24)));
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
            'reviewed_at' => 'datetime',
            'requested_starts_at' => 'datetime',
        ];
    }
}
