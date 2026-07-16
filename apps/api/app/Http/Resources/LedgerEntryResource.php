<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\LedgerEntry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin LedgerEntry
 */
final class LedgerEntryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'direction' => $this->direction->value,
            'amount_paise' => $this->amount_paise,
            'description' => $this->description,
            'occurred_at' => $this->occurred_at?->toIso8601String(),
        ];
    }
}
