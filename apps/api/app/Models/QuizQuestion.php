<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One MCQ within a quiz (PRD §6.5). `correct_index` is never serialized to a
 * student before they submit.
 *
 * @property int $id
 * @property int|null $tenant_id
 * @property int $quiz_id
 * @property string $prompt
 * @property array<int, string> $options
 * @property int $correct_index
 * @property string|null $explanation
 * @property int $position
 */
class QuizQuestion extends Model
{
    use BelongsToTenant;
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'tenant_id', 'quiz_id', 'prompt', 'options', 'correct_index', 'explanation', 'position',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'options' => 'array',
            'correct_index' => 'integer',
            'position' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Quiz, $this>
     */
    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class);
    }
}
