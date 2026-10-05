<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A Talent Pool candidate an employer shortlisted directly from the pool —
 * see the migration for why this is separate from EmployerJobApplication.
 *
 * @property int $id
 * @property int $employer_job_id
 * @property int $candidate_id
 * @property int|null $shortlisted_by_user_id
 */
final class TalentPoolShortlist extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'employer_job_id', 'candidate_id', 'shortlisted_by_user_id'];

    public function job(): BelongsTo
    {
        return $this->belongsTo(EmployerJob::class, 'employer_job_id');
    }

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(User::class, 'candidate_id');
    }
}
