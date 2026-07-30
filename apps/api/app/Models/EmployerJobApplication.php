<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EmployerApplicationStage;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\EmployerJobApplicationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A candidate's application to an employer JD (PRD-E F3). Graded
 * applications rank above ungraded ones; the MockInterview link is the
 * read-only evidence trail.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $employer_job_id
 * @property int $candidate_id
 * @property int|null $jd_mock_id
 * @property int|null $mock_interview_id
 * @property int|null $mock_score
 * @property Carbon|null $graded_at
 * @property EmployerApplicationStage $stage
 * @property array<int, array<string, mixed>>|null $knockout_answers
 * @property string|null $rejection_reason
 */
final class EmployerJobApplication extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<EmployerJobApplicationFactory> */
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'employer_job_id',
        'candidate_id',
        'jd_mock_id',
        'mock_interview_id',
        'mock_score',
        'graded_at',
        'stage',
        'knockout_answers',
        'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'stage' => EmployerApplicationStage::class,
            'knockout_answers' => 'array',
            'graded_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<EmployerJob, $this> */
    public function job(): BelongsTo
    {
        return $this->belongsTo(EmployerJob::class, 'employer_job_id');
    }

    /** @return BelongsTo<User, $this> */
    public function candidate(): BelongsTo
    {
        return $this->belongsTo(User::class, 'candidate_id');
    }

    /** @return BelongsTo<JdMock, $this> */
    public function jdMock(): BelongsTo
    {
        return $this->belongsTo(JdMock::class);
    }

    /** @return BelongsTo<MockInterview, $this> */
    public function mockInterview(): BelongsTo
    {
        return $this->belongsTo(MockInterview::class);
    }

    /** @return HasMany<ApplicationStageTransition, $this> */
    public function transitions(): HasMany
    {
        return $this->hasMany(ApplicationStageTransition::class);
    }

    /**
     * Graded-first ranking (PRD-E F3): graded applications by score
     * descending, then ungraded by recency.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeRanked(Builder $query): Builder
    {
        return $query
            ->orderByRaw('CASE WHEN graded_at IS NULL THEN 1 ELSE 0 END')
            ->orderByDesc('mock_score')
            ->orderByDesc('created_at');
    }
}
