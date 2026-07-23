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
 * One ingested, normalised job posting in the live feed (PRD §6.22; scraper
 * sources per ADR 0048). `extracted_skills` comes from the §6.21 JD pipeline
 * and drives per-student relevance scoring. `prep_questions` caches the
 * AI-generated potential interview questions — generated once per posting.
 *
 * @property int $id
 * @property int|null $tenant_id
 * @property int|null $job_feed_source_id
 * @property string|null $external_id
 * @property string $title
 * @property string $company
 * @property string|null $location
 * @property string|null $work_mode
 * @property string $description
 * @property string|null $apply_url
 * @property string $source_kind
 * @property string|null $role_title
 * @property list<string>|null $extracted_skills
 * @property list<array{question: string, why: string|null, source: string}>|null $prep_questions
 * @property string|null $seniority
 * @property int $quality_score
 * @property string $fingerprint
 * @property string $status
 * @property Carbon|null $posted_at
 * @property Carbon|null $expires_at
 * @property Carbon|null $ingested_at
 */
class JobFeedItem extends Model
{
    use BelongsToTenant;
    use HasFactory;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_EXPIRED = 'expired';

    /** @var list<string> */
    protected $fillable = [
        'tenant_id', 'job_feed_source_id', 'external_id', 'title', 'company', 'location',
        'work_mode', 'description', 'apply_url', 'source_kind', 'role_title',
        'extracted_skills', 'prep_questions', 'seniority', 'quality_score', 'fingerprint', 'status',
        'posted_at', 'expires_at', 'ingested_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'extracted_skills' => 'array',
            'prep_questions' => 'array',
            'quality_score' => 'integer',
            'posted_at' => 'datetime',
            'expires_at' => 'datetime',
            'ingested_at' => 'datetime',
        ];
    }

    /** Stable dedupe key: an external id when present, else company+title+location. */
    public static function fingerprintFor(?string $externalId, string $company, string $title, ?string $location): string
    {
        $basis = $externalId !== null && $externalId !== ''
            ? 'ext:'.$externalId
            : Str::of($company.'|'.$title.'|'.($location ?? ''))->lower()->squish()->value();

        return hash('sha256', $basis);
    }

    /**
     * @return BelongsTo<JobFeedSource, $this>
     */
    public function source(): BelongsTo
    {
        return $this->belongsTo(JobFeedSource::class, 'job_feed_source_id');
    }
}
