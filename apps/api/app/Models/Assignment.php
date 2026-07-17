<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Assignment content for a lesson (PRD §6.5). One per lesson (mirrors Quiz/CodingLab).
 * `rubric` is the trainer's grading criteria.
 *
 * @property int $id
 * @property int|null $tenant_id
 * @property int $lesson_id
 * @property string $title
 * @property string|null $instructions
 * @property array<int, array{criterion: string, max_points: int}>|null $rubric
 * @property int $max_points
 * @property bool $allow_link
 * @property bool $allow_file
 */
class Assignment extends Model
{
    use BelongsToTenant;
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'tenant_id', 'lesson_id', 'title', 'instructions', 'rubric', 'max_points', 'allow_link', 'allow_file',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rubric' => 'array',
            'max_points' => 'integer',
            'allow_link' => 'boolean',
            'allow_file' => 'boolean',
        ];
    }

    /** The max points for a rubric criterion by name (0 if unknown). */
    public function rubricMaxFor(string $criterion): int
    {
        foreach ($this->rubric ?? [] as $row) {
            if (($row['criterion'] ?? null) === $criterion) {
                return (int) ($row['max_points'] ?? 0);
            }
        }

        return 0;
    }

    /**
     * @return BelongsTo<Lesson, $this>
     */
    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    /**
     * @return HasMany<AssignmentSubmission, $this>
     */
    public function submissions(): HasMany
    {
        return $this->hasMany(AssignmentSubmission::class);
    }
}
