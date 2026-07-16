<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Product
 */
final class ProductResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sku' => $this->sku,
            'name' => $this->name,
            'feature' => $this->feature,
            'kind' => $this->kind->value,
            'price_paise' => $this->price_paise,
            'grant_amount' => $this->grant_amount,
            'period_days' => $this->period_days,
            'source_batch_id' => $this->source_batch_id,
            'active' => $this->active,
        ];
    }
}
