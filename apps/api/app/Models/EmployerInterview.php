<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EmployerInterviewRound;
use App\Enums\EmployerInterviewStatus;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\EmployerInterviewFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One L1/L2 interview round for an application (PRD-E F5).
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $employer_job_application_id
 * @property EmployerInterviewRound $round
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
        'round',
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
            'round' => EmployerInterviewRound::class,
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

    /** @return BelongsTo<EmployerJobApplication, $this> */
    public function application(): BelongsTo
    {
        return $this->belongsTo(EmployerJobApplication::class, 'employer_job_application_id');
    }
}
