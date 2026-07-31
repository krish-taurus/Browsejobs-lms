<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One round in a JD's interview process (PRD-E F18).
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $employer_job_id
 * @property int $position
 * @property string $key
 * @property string $name
 * @property string $kind
 * @property list<string>|null $focus_skills
 * @property array<string, int>|null $competency_weights
 * @property array<string, int>|null $format_mix
 * @property int|null $question_count
 * @property string|null $notes
 * @property int $window_hours
 * @property string $dispatch
 * @property int|null $auto_min_score
 * @property bool $enabled
 */
final class EmployerJobRound extends Model
{
    use BelongsToTenant;

    public const KIND_AI = 'ai_interview';

    public const KIND_MCQ = 'mcq';

    /**
     * A round the platform does not run: a call, an on-site, a panel. It is
     * tracked so the process is legible end to end, but nothing is generated
     * or graded for it — recording it as an AI round would report a score
     * nobody produced.
     */
    public const KIND_HUMAN = 'human';

    public const KINDS = [self::KIND_AI, self::KIND_MCQ, self::KIND_HUMAN];

    public const DISPATCH_MANUAL = 'manual';

    public const DISPATCH_AUTO = 'auto';

    public const DISPATCHES = [self::DISPATCH_MANUAL, self::DISPATCH_AUTO];

    /**
     * The two rounds every JD starts with, matching the L1/L2 stages.
     *
     * They carry a question count so the bank is split between them rather
     * than the first round consuming all of it and the second repeating —
     * which is what happened when both rounds simply snapshotted the whole
     * JD mock.
     */
    public const DEFAULTS = [
        ['key' => 'l1', 'name' => 'L1 — role fit', 'position' => 1, 'question_count' => 6],
        ['key' => 'l2', 'name' => 'L2 — depth', 'position' => 2, 'question_count' => 6],
    ];

    protected $fillable = [
        'tenant_id', 'employer_job_id', 'position', 'key', 'name', 'kind',
        'focus_skills', 'competency_weights', 'format_mix', 'question_count',
        'notes', 'window_hours', 'dispatch', 'auto_min_score', 'enabled',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'focus_skills' => 'array',
            'competency_weights' => 'array',
            'format_mix' => 'array',
            'question_count' => 'integer',
            'window_hours' => 'integer',
            'auto_min_score' => 'integer',
            'position' => 'integer',
            'enabled' => 'boolean',
        ];
    }

    /** @return BelongsTo<EmployerJob, $this> */
    public function job(): BelongsTo
    {
        return $this->belongsTo(EmployerJob::class, 'employer_job_id');
    }

    /** @return HasMany<EmployerInterview, $this> */
    public function interviews(): HasMany
    {
        return $this->hasMany(EmployerInterview::class, 'employer_job_round_id');
    }

    /**
     * Whether this round can go out without anyone pressing send.
     *
     * A threshold of null is deliberately not treated as "auto at any
     * score": an automatic round with no bar would interview everyone who
     * applied, which is the opposite of what the setting is for.
     */
    public function dispatchesAutomatically(): bool
    {
        return $this->enabled
            && $this->dispatch === self::DISPATCH_AUTO
            && $this->auto_min_score !== null
            && $this->kind !== self::KIND_HUMAN;
    }

    /** Rounds the platform generates and grades. */
    public function isPlatformRun(): bool
    {
        return $this->kind !== self::KIND_HUMAN;
    }
}
