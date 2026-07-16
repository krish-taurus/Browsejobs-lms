<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CodeLanguage;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Coding-lab content for a lesson (PRD §6.5). `test_cases` are hidden from the
 * student.
 *
 * @property int $id
 * @property int|null $tenant_id
 * @property int $lesson_id
 * @property CodeLanguage $language
 * @property string|null $instructions
 * @property string|null $starter_code
 * @property array<int, array{stdin?: string, expected_stdout: string}>|null $test_cases
 * @property int|null $time_limit_ms
 */
class CodingLab extends Model
{
    use BelongsToTenant;
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'tenant_id', 'lesson_id', 'language', 'instructions', 'starter_code', 'test_cases', 'time_limit_ms',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'language' => CodeLanguage::class,
            'test_cases' => 'array',
            'time_limit_ms' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Lesson, $this>
     */
    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }
}
