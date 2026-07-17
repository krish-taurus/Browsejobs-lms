<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DeflectionOutcome;
use App\Enums\TicketCategory;
use App\Enums\TutorConfidence;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * An AI first-response offered to a student before a ticket was raised (PRD §6.13).
 *
 * @property int $id
 * @property int|null $tenant_id
 * @property int $student_id
 * @property TicketCategory $category
 * @property string $question
 * @property string $answer
 * @property TutorConfidence $confidence
 * @property array<int, int>|null $cited_chunk_ids
 * @property DeflectionOutcome $outcome
 * @property int|null $ticket_id
 * @property int|null $ai_event_id
 * @property Carbon|null $resolved_at
 */
class TicketDeflection extends Model
{
    use BelongsToTenant;
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'tenant_id', 'student_id', 'category', 'question', 'answer', 'confidence',
        'cited_chunk_ids', 'outcome', 'ticket_id', 'ai_event_id', 'resolved_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => TicketCategory::class,
            'confidence' => TutorConfidence::class,
            'outcome' => DeflectionOutcome::class,
            'cited_chunk_ids' => 'array',
            'resolved_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    /**
     * @return BelongsTo<Ticket, $this>
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }
}
