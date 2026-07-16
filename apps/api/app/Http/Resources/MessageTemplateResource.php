<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\MessageTemplate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin MessageTemplate
 */
final class MessageTemplateResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'key' => $this->key,
            'channel' => $this->channel->value,
            'category' => $this->category->value,
            'name' => $this->name,
            'subject' => $this->subject,
            'body' => $this->body,
            'locale' => $this->locale,
            'active' => $this->active,
        ];
    }
}
