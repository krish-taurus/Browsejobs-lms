<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One CV version (PRD §6.7). `content` is the structured document
 * (headline/summary/skills/projects/certifications) the templates render;
 * `ats` caches the latest ATS report. Approval gates placement use.
 *
 * @property int $id
 * @property int|null $tenant_id
 * @property int $user_id
 * @property int|null $course_id
 * @property int $version
 * @property string $title
 * @property string $source
 * @property string $content_source
 * @property array<string, mixed> $content
 * @property string|null $jd_excerpt
 * @property array<string, mixed>|null $ats
 * @property string $status
 * @property int|null $approved_by
 * @property Carbon|null $approved_at
 * @property string|null $share_token
 */
class CvDocument extends Model
{
    use BelongsToTenant;
    use HasFactory;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_APPROVED = 'approved';

    public const SOURCE_AUTO = 'auto';

    public const SOURCE_MANUAL = 'manual';

    public const SOURCE_TAILORED = 'jd_tailored';

    /** @var list<string> */
    protected $fillable = [
        'tenant_id', 'user_id', 'course_id', 'version', 'title', 'source',
        'content_source', 'content', 'jd_excerpt', 'ats', 'status',
        'approved_by', 'approved_at', 'share_token',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public static function nextVersionFor(User $student): int
    {
        return (int) self::query()->where('user_id', $student->id)->max('version') + 1;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'content' => 'array',
            'ats' => 'array',
            'approved_at' => 'datetime',
        ];
    }
}
