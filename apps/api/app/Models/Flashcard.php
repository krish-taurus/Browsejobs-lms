<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A flashcard for a lesson (front prompt / back answer). Reviewed by students on
 * an SM-2 spaced schedule via FlashcardReview.
 *
 * @property int $id
 * @property int|null $tenant_id
 * @property int $lesson_id
 * @property string $front
 * @property string $back
 * @property string $source
 * @property int $position
 */
final class Flashcard extends Model
{
    use BelongsToTenant;

    /** @var list<string> */
    protected $fillable = ['tenant_id', 'lesson_id', 'front', 'back', 'source', 'position'];

    protected function casts(): array
    {
        return ['position' => 'integer'];
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    /**
     * @return HasMany<FlashcardReview, $this>
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(FlashcardReview::class);
    }
}
