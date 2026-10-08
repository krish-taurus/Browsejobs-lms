<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One AI mock-interview session (PRD §6.6). Text mode in P4.1; the voice
 * transport (P4.3) reuses this record with mode='voice'.
 *
 * @property int $id
 * @property int|null $tenant_id
 * @property int $user_id
 * @property int $mock_blueprint_id
 * @property string $mode
 * @property string $status
 * @property int|null $overall_score
 * @property array<string, mixed>|null $scorecard
 * @property string|null $scorecard_source
 * @property Carbon $started_at
 * @property Carbon|null $completed_at
 */
class MockInterview extends Model
{
    use BelongsToTenant;

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_COMPLETED = 'completed';

    // Voice session that ended without enough signal to grade (P4.3) —
    // the consumed credit is refunded and no scorecard exists.
    public const STATUS_ABANDONED = 'abandoned';

    public const MODE_TEXT = 'text';

    public const MODE_VOICE = 'voice';

    // Which of the four interview experiences a session belongs to. Derived,
    // not stored: the blueprint says who the interview is for (a course, an
    // employer job / job-feed posting, or one student's CV), is_room/mode say
    // how it was taken. Each kind has its own portal URL and its own list.
    public const KIND_PRACTICE = 'practice'; // course blueprint, typed

    public const KIND_VOICE = 'voice'; // course blueprint, spoken (room or call)

    public const KIND_JOB = 'job'; // a role hiring directly / a job-feed posting

    public const KIND_CV = 'cv'; // the free CV-readiness interview

    /** @var list<string> */
    public const KINDS = [self::KIND_PRACTICE, self::KIND_VOICE, self::KIND_JOB, self::KIND_CV];

    /** @var list<string> */
    protected $fillable = [
        'tenant_id', 'user_id', 'mock_blueprint_id', 'mode', 'is_room', 'status',
        'overall_score', 'scorecard', 'scorecard_source', 'started_at', 'completed_at',
        'provider_session_id', 'join_url', 'recording_url', 'duration_seconds', 'cost_micros',
    ];

    /**
     * @return BelongsTo<MockBlueprint, $this>
     */
    public function blueprint(): BelongsTo
    {
        return $this->belongsTo(MockBlueprint::class, 'mock_blueprint_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return HasMany<MockTurn, $this>
     */
    public function turns(): HasMany
    {
        return $this->hasMany(MockTurn::class);
    }

    /**
     * The interview's kind — precedence cv > job > voice > practice, the same
     * order scopeOfKind() uses so a list and a single record never disagree.
     */
    public function kind(): string
    {
        $blueprint = $this->blueprint;

        if ($blueprint?->user_id !== null) {
            return self::KIND_CV;
        }
        if ($blueprint?->employer_job_id !== null || $blueprint?->job_feed_item_id !== null) {
            return self::KIND_JOB;
        }
        if ($this->is_room || $this->mode === self::MODE_VOICE) {
            return self::KIND_VOICE;
        }

        return self::KIND_PRACTICE;
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeOfKind(Builder $query, string $kind): Builder
    {
        $courseOnly = fn (Builder $b) => $b->whereNull('user_id')
            ->whereNull('employer_job_id')
            ->whereNull('job_feed_item_id');

        return match ($kind) {
            self::KIND_CV => $query->whereHas('blueprint', fn (Builder $b) => $b->whereNotNull('user_id')),
            self::KIND_JOB => $query->whereHas('blueprint', fn (Builder $b) => $b->whereNull('user_id')
                ->where(fn (Builder $w) => $w->whereNotNull('employer_job_id')->orWhereNotNull('job_feed_item_id'))),
            self::KIND_VOICE => $query->whereHas('blueprint', $courseOnly)
                ->where(fn (Builder $w) => $w->where('is_room', true)->orWhere('mode', self::MODE_VOICE)),
            default => $query->whereHas('blueprint', $courseOnly)
                ->where('is_room', false)
                ->where('mode', '!=', self::MODE_VOICE),
        };
    }

    public function candidateAnswers(): int
    {
        return $this->turns()->where('role', MockTurn::ROLE_CANDIDATE)->count();
    }

    public function interviewerQuestions(): int
    {
        return $this->turns()->where('role', MockTurn::ROLE_INTERVIEWER)->count();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scorecard' => 'array',
            'overall_score' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }
}
