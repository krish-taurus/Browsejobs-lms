<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * One hiring-partner feedback record (PRD §6.21). Requested by a placement
 * officer for a candidate's interview; the company submits via the public token.
 *
 * @property int $id
 * @property int|null $tenant_id
 * @property int|null $job_application_id
 * @property int|null $course_id
 * @property string $company
 * @property string $role_title
 * @property string $token
 * @property string $status
 * @property array<string, int>|null $ratings
 * @property int|null $overall
 * @property bool|null $would_hire
 * @property string|null $strengths
 * @property string|null $gaps
 * @property int|null $requested_by
 * @property Carbon|null $submitted_at
 */
class HiringPartnerFeedback extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $table = 'hiring_partner_feedback';

    public const STATUS_REQUESTED = 'requested';

    public const STATUS_SUBMITTED = 'submitted';

    /** @var list<string> */
    protected $fillable = [
        'tenant_id', 'job_application_id', 'course_id', 'company', 'role_title',
        'token', 'status', 'ratings', 'overall', 'would_hire', 'strengths',
        'gaps', 'requested_by', 'submitted_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ratings' => 'array',
            'overall' => 'integer',
            'would_hire' => 'boolean',
            'submitted_at' => 'datetime',
        ];
    }

    public static function newToken(): string
    {
        return Str::random(48);
    }

    /**
     * @return BelongsTo<JobApplication, $this>
     */
    public function application(): BelongsTo
    {
        return $this->belongsTo(JobApplication::class, 'job_application_id');
    }
}
