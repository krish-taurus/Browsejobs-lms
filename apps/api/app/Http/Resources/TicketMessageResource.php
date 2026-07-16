<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\TicketMessage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * @mixin TicketMessage
 */
final class TicketMessageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'author_type' => $this->author_type->value,
            'author_name' => $this->whenLoaded('author', fn () => $this->author?->name),
            'body' => $this->body,
            'is_internal' => $this->is_internal,
            'channel' => $this->channel,
            'created_at' => $this->created_at?->toIso8601String(),
            'attachments' => $this->whenLoaded('attachments', fn () => $this->attachments->map(fn ($a) => [
                'id' => $a->id,
                'name' => $a->original_name,
                'url' => $this->signed($a->path),
            ])->all()),
        ];
    }

    private function signed(string $path): ?string
    {
        try {
            return Storage::disk('s3')->temporaryUrl($path, now()->addHour());
        } catch (Throwable) {
            return null;
        }
    }
}
