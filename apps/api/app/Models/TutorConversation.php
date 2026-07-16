<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A student's AI Tutor conversation thread (PRD §6.4).
 *
 * @property int $id
 * @property int|null $tenant_id
 * @property int $student_id
 * @property int|null $lesson_id
 * @property string|null $title
 * @property Carbon|null $last_message_at
 */
class TutorConversation extends Model
{
    use BelongsToTenant;
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'tenant_id', 'student_id', 'lesson_id', 'title', 'last_message_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_message_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<TutorMessage, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(TutorMessage::class, 'conversation_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    /**
     * @return BelongsTo<Lesson, $this>
     */
    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }
}
