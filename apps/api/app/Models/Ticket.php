<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TicketCategory;
use App\Enums\TicketPriority;
use App\Enums\TicketSentiment;
use App\Enums\TicketStatus;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A student support ticket (PRD §6.13). Routed on create with per-category SLA
 * due-times; CSAT + escalation are recorded inline.
 *
 * @property int $id
 * @property int|null $tenant_id
 * @property string $reference
 * @property int $student_id
 * @property int|null $lead_id
 * @property TicketCategory $category
 * @property TicketPriority $priority
 * @property TicketStatus $status
 * @property int|null $assignee_id
 * @property int|null $support_team_id
 * @property string $subject
 * @property Carbon|null $first_response_due_at
 * @property Carbon|null $resolution_due_at
 * @property Carbon|null $first_response_at
 * @property Carbon|null $resolved_at
 * @property Carbon|null $closed_at
 * @property Carbon|null $escalated_at
 * @property int|null $csat_rating
 * @property TicketCategory|null $ai_category
 * @property TicketPriority|null $ai_urgency
 * @property TicketSentiment|null $ai_sentiment
 * @property bool $ai_priority_raised
 * @property int|null $ai_duplicate_of_id
 * @property Carbon|null $ai_triaged_at
 * @property int|null $ai_event_id
 */
class Ticket extends Model
{
    use BelongsToTenant;
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'tenant_id', 'reference', 'student_id', 'lead_id', 'category', 'priority',
        'status', 'assignee_id', 'support_team_id', 'subject', 'first_response_due_at',
        'resolution_due_at', 'first_response_at', 'resolved_at', 'closed_at', 'reopened_at',
        'response_warned_at', 'breached_at', 'resolution_breached_at', 'escalated_at',
        'escalated_to_id', 'csat_rating', 'csat_comment', 'csat_at',
        'ai_category', 'ai_urgency', 'ai_sentiment', 'ai_priority_raised',
        'ai_duplicate_of_id', 'ai_triaged_at', 'ai_event_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => TicketCategory::class,
            'priority' => TicketPriority::class,
            'status' => TicketStatus::class,
            'ai_category' => TicketCategory::class,
            'ai_urgency' => TicketPriority::class,
            'ai_sentiment' => TicketSentiment::class,
            'ai_priority_raised' => 'boolean',
            'ai_triaged_at' => 'datetime',
            'csat_rating' => 'integer',
            'first_response_due_at' => 'datetime',
            'resolution_due_at' => 'datetime',
            'first_response_at' => 'datetime',
            'resolved_at' => 'datetime',
            'closed_at' => 'datetime',
            'reopened_at' => 'datetime',
            'response_warned_at' => 'datetime',
            'breached_at' => 'datetime',
            'resolution_breached_at' => 'datetime',
            'escalated_at' => 'datetime',
            'csat_at' => 'datetime',
        ];
    }

    public function isOpen(): bool
    {
        return $this->status->isActive();
    }

    /** Resolved/closed and still inside the reopen window. */
    public function reopenable(): bool
    {
        if (! in_array($this->status, [TicketStatus::Resolved, TicketStatus::Closed], true)) {
            return false;
        }

        $since = $this->resolved_at ?? $this->closed_at;
        $days = (int) config('support.reopen_days', 7);

        return $since !== null && $since->diffInDays(now()) < $days;
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    /**
     * @return BelongsTo<SupportTeam, $this>
     */
    public function supportTeam(): BelongsTo
    {
        return $this->belongsTo(SupportTeam::class);
    }

    /**
     * @return BelongsTo<Lead, $this>
     */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    /**
     * @return HasMany<TicketMessage, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(TicketMessage::class);
    }

    /**
     * @return HasMany<TicketAttachment, $this>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(TicketAttachment::class);
    }
}
