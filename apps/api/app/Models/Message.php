<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MessageCategory;
use App\Enums\MessageChannel;
use App\Enums\MessageDirection;
use App\Enums\MessageStatus;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A logged message — outbound send or inbound reply (PRD §6.9 / §8 `messages`).
 *
 * @property int $id
 * @property int|null $tenant_id
 * @property int|null $user_id
 * @property int|null $lead_id
 * @property MessageDirection $direction
 * @property MessageChannel $channel
 * @property string|null $template_key
 * @property MessageCategory $category
 * @property string|null $recipient
 * @property string|null $subject
 * @property string|null $body
 * @property MessageStatus $status
 * @property string|null $suppressed_reason
 * @property string|null $provider_message_id
 * @property Carbon|null $sent_at
 * @property array<string, mixed>|null $meta
 */
class Message extends Model
{
    use BelongsToTenant;
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'tenant_id', 'user_id', 'lead_id', 'direction', 'channel', 'template_key', 'category',
        'recipient', 'subject', 'body', 'status', 'suppressed_reason', 'provider_message_id',
        'sent_at', 'delivered_at', 'read_at', 'failed_reason', 'meta',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'direction' => MessageDirection::class,
            'channel' => MessageChannel::class,
            'category' => MessageCategory::class,
            'status' => MessageStatus::class,
            'sent_at' => 'datetime',
            'delivered_at' => 'datetime',
            'read_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Lead, $this>
     */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }
}
