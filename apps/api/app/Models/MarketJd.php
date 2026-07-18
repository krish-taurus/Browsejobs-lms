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
 * One imported job-market JD (PRD §6.21). The AI parse stage fills
 * `extracted_skills`; the aggregate frequency of those skills per role/course is
 * the market-demand signal for Curriculum Intelligence. Placement-team owned;
 * never scraped, never student-visible.
 *
 * @property int $id
 * @property int|null $tenant_id
 * @property int|null $course_id
 * @property string $source
 * @property string|null $external_ref
 * @property string $role_title
 * @property string|null $company
 * @property string|null $location
 * @property string|null $seniority
 * @property string $raw_jd
 * @property string $fingerprint
 * @property list<string>|null $extracted_skills
 * @property int $quality_score
 * @property string $parse_status
 * @property Carbon|null $parsed_at
 * @property Carbon|null $ingested_at
 */
class MarketJd extends Model
{
    use BelongsToTenant;
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_PARSED = 'parsed';

    public const STATUS_FAILED = 'failed';

    /** @var list<string> */
    protected $fillable = [
        'tenant_id', 'course_id', 'source', 'external_ref', 'role_title', 'company',
        'location', 'seniority', 'raw_jd', 'fingerprint', 'extracted_skills',
        'quality_score', 'parse_status', 'parsed_at', 'ingested_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'extracted_skills' => 'array',
            'quality_score' => 'integer',
            'parsed_at' => 'datetime',
            'ingested_at' => 'datetime',
        ];
    }

    /**
     * Stable dedupe key for a JD: normalised role + a hash of the JD body. Two
     * identical postings (same role, same text) collapse to one row so the
     * demand counts aren't inflated by re-imports.
     */
    public static function fingerprintFor(string $roleTitle, string $rawJd): string
    {
        $role = Str::of($roleTitle)->lower()->squish()->value();
        $body = Str::of($rawJd)->lower()->replaceMatches('/\s+/', ' ')->trim()->value();

        return hash('sha256', $role.'|'.$body);
    }

    /**
     * @return BelongsTo<Course, $this>
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }
}
