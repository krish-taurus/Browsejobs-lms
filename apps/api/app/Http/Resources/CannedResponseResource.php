<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\CannedResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CannedResponse
 */
final class CannedResponseResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'body' => $this->body,
            'category' => $this->category,
            'active' => $this->active,
        ];
    }
}
