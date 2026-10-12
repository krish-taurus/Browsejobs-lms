<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One person the shortlist agent picked for a JD, with the reasons. Either a
 * BrowseJobs student (candidate_user_id) or a client/Naukri candidate
 * (employer_candidate_id). "removed" is the client taking someone off the
 * list; the agent never re-adds a removed person on a later run.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $employer_job_id
 * @property int|null $job_shortlist_run_id
 * @property string $source
 * @property int|null $candidate_user_id
 * @property int|null $employer_candidate_id
 * @property string $candidate_key
 * @property int $match_score
 * @property int $rank
 * @property array<string, mixed> $reasons
 * @property string $status
 */
final class JobShortlistEntry extends Model
{
    use BelongsToTenant;

    public const SOURCE_BROWSEJOBS = 'browsejobs';

    public const SOURCES = [self::SOURCE_BROWSEJOBS, EmployerCandidate::SOURCE_CLIENT_DB, EmployerCandidate::SOURCE_NAUKRI];

    public const STATUS_SHORTLISTED = 'shortlisted';

    public const STATUS_REMOVED = 'removed';

    protected $fillable = [
        'tenant_id', 'employer_job_id', 'job_shortlist_run_id', 'source', 'candidate_user_id', 'employer_candidate_id',
        'candidate_key', 'match_score', 'rank', 'reasons', 'status', 'removed_by_user_id', 'removed_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'reasons' => 'array',
            'match_score' => 'integer',
            'rank' => 'integer',
            'removed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function candidateUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'candidate_user_id');
    }

    /** @return BelongsTo<EmployerCandidate, $this> */
    public function employerCandidate(): BelongsTo
    {
        return $this->belongsTo(EmployerCandidate::class);
    }

    /** @return BelongsTo<EmployerJob, $this> */
    public function job(): BelongsTo
    {
        return $this->belongsTo(EmployerJob::class, 'employer_job_id');
    }
}
