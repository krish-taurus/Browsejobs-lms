<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A file attached to a ticket message (PRD §6.13). Stored on s3.
 *
 * @property int $id
 * @property int|null $tenant_id
 * @property int $ticket_id
 * @property int|null $ticket_message_id
 * @property string $path
 * @property string $original_name
 * @property string|null $mime
 * @property int $size_bytes
 */
class TicketAttachment extends Model
{
    use BelongsToTenant;
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'tenant_id', 'ticket_id', 'ticket_message_id', 'path', 'original_name', 'mime', 'size_bytes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Ticket, $this>
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }
}
