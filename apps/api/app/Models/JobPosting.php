<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A staff-curated job posting (PRD §6.11). Real openings entered by the
 * placement team — never scraped, never fabricated.
 *
 * @property int $id
 * @property int|null $tenant_id
 * @property int|null $course_id
 * @property int|null $posted_by
 * @property string $title
 * @property string $company
 * @property string|null $location
 * @property string|null $work_mode
 * @property string|null $salary_range
 * @property string $description
 * @property string|null $apply_url
 * @property string $status
 * @property Carbon|null $closes_on
 */
class JobPosting extends Model
{
    use BelongsToTenant;
    use HasFactory;

    public const STATUS_OPEN = 'open';

    public const STATUS_CLOSED = 'closed';

    /** @var list<string> */
    protected $fillable = [
        'tenant_id', 'course_id', 'posted_by', 'title', 'company', 'location',
        'work_mode', 'salary_range', 'description', 'apply_url', 'status', 'closes_on',
    ];

    /**
     * @return BelongsTo<Course, $this>
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * @return HasMany<JobApplication, $this>
     */
    public function applications(): HasMany
    {
        return $this->hasMany(JobApplication::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['closes_on' => 'date'];
    }
}
