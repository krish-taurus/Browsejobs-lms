<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A role-specific interview blueprint per course (PRD §6.6): the role, the
 * competencies scored on the card, and the opening question. P4.2 enriches
 * questioning from the real-interview bank behind the same model.
 *
 * @property int $id
 * @property int|null $tenant_id
 * @property int $course_id
 * @property string $role_title
 * @property list<string> $competencies
 * @property string $opening_question
 * @property bool $is_active
 */
class MockBlueprint extends Model
{
    use BelongsToTenant;
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'tenant_id', 'course_id', 'role_title', 'competencies', 'opening_question', 'is_active',
    ];

    /**
     * @return BelongsTo<Course, $this>
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'competencies' => 'array',
            'is_active' => 'boolean',
        ];
    }
}
