<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One turn in an AI Tutor conversation (PRD §6.4).
 *
 * @property int $id
 * @property int|null $tenant_id
 * @property int $conversation_id
 * @property int $student_id
 * @property string $role
 * @property string $body
 * @property array<int, int>|null $cited_chunk_ids
 * @property string|null $confidence
 * @property bool $escalated
 * @property int|null $ticket_id
 * @property string|null $question_fingerprint
 * @property int|null $ai_event_id
 */
class TutorMessage extends Model
{
    use BelongsToTenant;
    use HasFactory;

    public const ROLE_STUDENT = 'student';

    public const ROLE_TUTOR = 'tutor';

    /** @var list<string> */
    protected $fillable = [
        'tenant_id', 'conversation_id', 'student_id', 'role', 'body', 'cited_chunk_ids',
        'confidence', 'escalated', 'ticket_id', 'question_fingerprint', 'ai_event_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cited_chunk_ids' => 'array',
            'escalated' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<TutorConversation, $this>
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(TutorConversation::class, 'conversation_id');
    }

    /**
     * @return BelongsTo<Ticket, $this>
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }
}
