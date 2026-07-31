<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EmployerInterviewStatus;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\EmployerInterviewFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One interview round for an application (PRD-E F5, F18).
 *
 * `round` holds the key of the EmployerJobRound it was sent from. It is a
 * plain string rather than an enum because employers define their own rounds
 * now; the two the platform ships with keep the keys `l1` and `l2`, so rows
 * written before F18 read back unchanged.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $employer_job_application_id
 * @property string $round
 * @property string|null $round_name
 * @property int|null $employer_job_round_id
 * @property EmployerInterviewStatus $status
 * @property array<int, array<string, mixed>> $question_set
 * @property array<string, mixed> $rubric
 * @property array<int, array<string, mixed>>|null $answers
 * @property array<string, int>|null $dimension_scores
 * @property int|null $overall_score
 * @property string|null $grading_summary
 * @property string|null $grading_source
 * @property Carbon $invited_at
 * @property Carbon $expires_at
 * @property Carbon|null $started_at
 * @property Carbon|null $submitted_at
 * @property Carbon|null $graded_at
 */
final class EmployerInterview extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<EmployerInterviewFactory> */
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'employer_job_application_id',
        'employer_job_round_id',
        'round',
        'round_name',
        'status',
        'question_set',
        'rubric',
        'answers',
        'dimension_scores',
        'overall_score',
        'grading_summary',
        'grading_source',
        'invited_at',
        'expires_at',
        'started_at',
        'submitted_at',
        'graded_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => EmployerInterviewStatus::class,
            'question_set' => 'array',
            'rubric' => 'array',
            'answers' => 'array',
            'dimension_scores' => 'array',
            'invited_at' => 'datetime',
            'expires_at' => 'datetime',
            'started_at' => 'datetime',
            'submitted_at' => 'datetime',
            'graded_at' => 'datetime',
        ];
    }

    /**
     * The round definition this interview was sent from. Null for rounds
     * created before F18 — a definition invented for them would be a record
     * of a decision nobody made.
     *
     * @return BelongsTo<EmployerJobRound, $this>
     */
    public function roundDefinition(): BelongsTo
    {
        return $this->belongsTo(EmployerJobRound::class, 'employer_job_round_id');
    }

    /** @return BelongsTo<EmployerJobApplication, $this> */
    public function application(): BelongsTo
    {
        return $this->belongsTo(EmployerJobApplication::class, 'employer_job_application_id');
    }
}
