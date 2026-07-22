<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One student's SM-2 schedule for one flashcard. `due_at` decides when the card
 * next surfaces in the review queue.
 *
 * @property int $id
 * @property int|null $tenant_id
 * @property int $user_id
 * @property int $flashcard_id
 * @property float $ease
 * @property int $interval_days
 * @property int $reps
 * @property int $lapses
 * @property Carbon|null $due_at
 * @property Carbon|null $last_reviewed_at
 */
final class FlashcardReview extends Model
{
    use BelongsToTenant;

    /** @var list<string> */
    protected $fillable = [
        'tenant_id', 'user_id', 'flashcard_id', 'ease', 'interval_days',
        'reps', 'lapses', 'due_at', 'last_reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'ease' => 'float',
            'interval_days' => 'integer',
            'reps' => 'integer',
            'lapses' => 'integer',
            'due_at' => 'datetime',
            'last_reviewed_at' => 'datetime',
        ];
    }

    public function flashcard(): BelongsTo
    {
        return $this->belongsTo(Flashcard::class);
    }
}
