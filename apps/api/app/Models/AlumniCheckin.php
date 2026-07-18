<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One alumni post-placement check-in (PRD §6.21). Auto-scheduled at 6 and 12
 * months from a placement; the alumnus responds in-portal, feeding long-term
 * outcome data and the referral pipeline.
 *
 * @property int $id
 * @property int|null $tenant_id
 * @property int $user_id
 * @property int|null $job_application_id
 * @property string $milestone
 * @property string $status
 * @property Carbon $scheduled_for
 * @property Carbon|null $responded_at
 * @property bool|null $still_employed
 * @property string|null $current_company
 * @property int|null $current_ctc_paise
 * @property bool|null $would_refer
 * @property string|null $notes
 */
class AlumniCheckin extends Model
{
    use BelongsToTenant;
    use HasFactory;

    public const MILESTONE_6M = 'm6';

    public const MILESTONE_12M = 'm12';

    public const STATUS_SCHEDULED = 'scheduled';

    public const STATUS_RESPONDED = 'responded';

    /** Months after placement for each milestone. */
    public const MILESTONE_MONTHS = [self::MILESTONE_6M => 6, self::MILESTONE_12M => 12];

    /** @var list<string> */
    protected $fillable = [
        'tenant_id', 'user_id', 'job_application_id', 'milestone', 'status',
        'scheduled_for', 'responded_at', 'still_employed', 'current_company',
        'current_ctc_paise', 'would_refer', 'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scheduled_for' => 'date',
            'responded_at' => 'datetime',
            'still_employed' => 'boolean',
            'current_ctc_paise' => 'integer',
            'would_refer' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
