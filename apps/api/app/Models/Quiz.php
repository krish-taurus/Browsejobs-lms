<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\QuizSource;
use App\Enums\QuizStatus;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Quiz content for a `quiz` lesson (PRD §6.5). One per lesson (mirrors CodingLab).
 *
 * @property int $id
 * @property int|null $tenant_id
 * @property int $lesson_id
 * @property string $title
 * @property string|null $instructions
 * @property int $time_limit_sec
 * @property int $pass_pct
 * @property bool $shuffle
 * @property QuizStatus $status
 * @property QuizSource $source
 * @property int|null $approved_by
 * @property Carbon|null $approved_at
 */
class Quiz extends Model
{
    use BelongsToTenant;
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'tenant_id', 'lesson_id', 'title', 'instructions', 'time_limit_sec', 'pass_pct',
        'shuffle', 'status', 'source', 'approved_by', 'approved_at', 'pdf_path', 'pdf_uploaded',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'time_limit_sec' => 'integer',
            'pass_pct' => 'integer',
            'shuffle' => 'boolean',
            'status' => QuizStatus::class,
            'source' => QuizSource::class,
            'approved_at' => 'datetime',
            'pdf_uploaded' => 'boolean',
        ];
    }

    /** Approved and has at least one question — safe to dispatch. */
    public function isDispatchable(): bool
    {
        return $this->status === QuizStatus::Approved && $this->questions()->exists();
    }

    /**
     * @return BelongsTo<Lesson, $this>
     */
    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    /**
     * @return HasMany<QuizQuestion, $this>
     */
    public function questions(): HasMany
    {
        return $this->hasMany(QuizQuestion::class)->orderBy('position');
    }

    /**
     * @return HasMany<QuizAttempt, $this>
     */
    public function attempts(): HasMany
    {
        return $this->hasMany(QuizAttempt::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
