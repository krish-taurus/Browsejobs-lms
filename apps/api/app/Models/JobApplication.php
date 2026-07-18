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
 * One student's run at one posting (PRD §6.11): the officer-managed stage
 * pipeline, offer details, placement timestamp, and the consent flags for
 * celebrating a win.
 *
 * @property int $id
 * @property int|null $tenant_id
 * @property int $job_posting_id
 * @property int $user_id
 * @property int|null $cv_document_id
 * @property string $status
 * @property string|null $note
 * @property int|null $offer_ctc_paise
 * @property string|null $offer_role
 * @property Carbon|null $offer_accepted_at
 * @property Carbon|null $placed_at
 * @property Carbon|null $celebrate_consent_at
 * @property bool $celebrate_anonymous
 */
class JobApplication extends Model
{
    use BelongsToTenant;
    use HasFactory;

    public const STATUS_APPLIED = 'applied';

    public const STATUS_SHORTLISTED = 'shortlisted';

    public const STATUS_INTERVIEWING = 'interviewing';

    public const STATUS_OFFER = 'offer';

    public const STATUS_PLACED = 'placed';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_WITHDRAWN = 'withdrawn';

    /** The officer-managed pipeline, in board order. */
    public const PIPELINE = [
        self::STATUS_APPLIED, self::STATUS_SHORTLISTED, self::STATUS_INTERVIEWING,
        self::STATUS_OFFER, self::STATUS_PLACED, self::STATUS_REJECTED, self::STATUS_WITHDRAWN,
    ];

    /** @var list<string> */
    protected $fillable = [
        'tenant_id', 'job_posting_id', 'job_feed_item_id', 'user_id', 'cv_document_id', 'status', 'note',
        'offer_ctc_paise', 'offer_role', 'offer_accepted_at', 'placed_at',
        'celebrate_consent_at', 'celebrate_anonymous',
    ];

    /**
     * @return BelongsTo<JobPosting, $this>
     */
    public function posting(): BelongsTo
    {
        return $this->belongsTo(JobPosting::class, 'job_posting_id');
    }

    /**
     * @return BelongsTo<JobFeedItem, $this>
     */
    public function feedItem(): BelongsTo
    {
        return $this->belongsTo(JobFeedItem::class, 'job_feed_item_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return HasMany<JobApplicationRound, $this>
     */
    public function rounds(): HasMany
    {
        return $this->hasMany(JobApplicationRound::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'offer_ctc_paise' => 'integer',
            'offer_accepted_at' => 'datetime',
            'placed_at' => 'datetime',
            'celebrate_consent_at' => 'datetime',
            'celebrate_anonymous' => 'boolean',
        ];
    }
}
