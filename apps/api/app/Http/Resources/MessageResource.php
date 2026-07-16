<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Message
 */
final class MessageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'direction' => $this->direction->value,
            'channel' => $this->channel->value,
            'category' => $this->category->value,
            'template_key' => $this->template_key,
            'recipient' => $this->recipient,
            'subject' => $this->subject,
            'body' => $this->body,
            'status' => $this->status->value,
            'suppressed_reason' => $this->suppressed_reason,
            'sent_at' => $this->sent_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
