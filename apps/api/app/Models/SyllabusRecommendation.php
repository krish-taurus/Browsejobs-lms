<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One Syllabus Recommendation Report for a course (PRD §6.21). Advisory: the
 * `items` are evidence-backed add/expand/deprioritise proposals; approving one is
 * a recorded decision that a trainer then applies through curriculum editing.
 *
 * @property int $id
 * @property int|null $tenant_id
 * @property int $course_id
 * @property string $status
 * @property string $source
 * @property string $content_source
 * @property string|null $summary
 * @property list<array<string, mixed>> $items
 * @property array<string, mixed> $evidence
 * @property int $market_sample
 * @property int|null $generated_by
 * @property int|null $reviewed_by
 * @property string|null $review_note
 * @property Carbon|null $reviewed_at
 */
class SyllabusRecommendation extends Model
{
    use BelongsToTenant;
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const SOURCE_ON_DEMAND = 'on_demand';

    public const SOURCE_SCHEDULED = 'scheduled';

    /** @var list<string> */
    protected $fillable = [
        'tenant_id', 'course_id', 'status', 'source', 'content_source', 'summary',
        'items', 'evidence', 'market_sample', 'generated_by', 'reviewed_by',
        'review_note', 'reviewed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'items' => 'array',
            'evidence' => 'array',
            'market_sample' => 'integer',
            'reviewed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Course, $this>
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
