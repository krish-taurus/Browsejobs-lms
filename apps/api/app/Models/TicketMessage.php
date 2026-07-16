<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TicketAuthorType;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One message in a ticket thread (PRD §6.13).
 *
 * @property int $id
 * @property int|null $tenant_id
 * @property int $ticket_id
 * @property int|null $author_id
 * @property TicketAuthorType $author_type
 * @property string $body
 * @property bool $is_internal
 * @property string $channel
 */
class TicketMessage extends Model
{
    use BelongsToTenant;
    use HasFactory;

    public const CHANNEL_PORTAL = 'portal';

    public const CHANNEL_WHATSAPP = 'whatsapp';

    public const CHANNEL_EMAIL = 'email';

    /** @var list<string> */
    protected $fillable = [
        'tenant_id', 'ticket_id', 'author_id', 'author_type', 'body', 'is_internal', 'channel',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'author_type' => TicketAuthorType::class,
            'is_internal' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Ticket, $this>
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * @return HasMany<TicketAttachment, $this>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(TicketAttachment::class);
    }
}
