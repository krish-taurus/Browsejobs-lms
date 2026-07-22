<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A publicly displayed interview question for a course, grouped by round — the
 * study popup on course-page placement cards.
 *
 * @property int $id
 * @property int|null $tenant_id
 * @property int $course_id
 * @property string|null $company
 * @property int $round_no
 * @property string $round_name
 * @property string $question
 * @property bool $is_published
 * @property int $position
 */
final class CourseInterviewQuestion extends Model
{
    use BelongsToTenant;

    /** @var list<string> */
    protected $fillable = [
        'tenant_id', 'course_id', 'company', 'round_no', 'round_name', 'question', 'is_published', 'position',
    ];

    protected function casts(): array
    {
        return [
            'round_no' => 'integer',
            'is_published' => 'boolean',
            'position' => 'integer',
        ];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }
}
