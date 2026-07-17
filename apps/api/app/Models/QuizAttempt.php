<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\QuizAttemptStatus;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A student's attempt at a quiz (PRD §6.5). One per (quiz, student); also tracks
 * the 48h/96h dispatch reminder ladder via `reminded_at`/`flagged_at`.
 *
 * @property int $id
 * @property int|null $tenant_id
 * @property int $quiz_id
 * @property int $user_id
 * @property QuizAttemptStatus $status
 * @property Carbon|null $deadline_at
 * @property Carbon|null $started_at
 * @property Carbon|null $submitted_at
 * @property int|null $score_pct
 * @property int|null $correct_count
 * @property int|null $total_count
 * @property array<string, int>|null $answers
 * @property array<string, int>|null $integrity
 * @property Carbon|null $reminded_at
 * @property Carbon|null $flagged_at
 */
class QuizAttempt extends Model
{
    use BelongsToTenant;
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'tenant_id', 'quiz_id', 'user_id', 'status', 'deadline_at', 'started_at', 'submitted_at',
        'score_pct', 'correct_count', 'total_count', 'answers', 'integrity', 'reminded_at', 'flagged_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => QuizAttemptStatus::class,
            'deadline_at' => 'datetime',
            'started_at' => 'datetime',
            'submitted_at' => 'datetime',
            'score_pct' => 'integer',
            'correct_count' => 'integer',
            'total_count' => 'integer',
            'answers' => 'array',
            'integrity' => 'array',
            'reminded_at' => 'datetime',
            'flagged_at' => 'datetime',
        ];
    }

    /** True when the deadline has passed (server-authoritative timer). */
    public function isLate(?Carbon $now = null): bool
    {
        return $this->deadline_at !== null && ($now ?? now())->greaterThan($this->deadline_at);
    }

    /**
     * @return BelongsTo<Quiz, $this>
     */
    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
