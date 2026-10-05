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
            'mock_bonus_amount' => $this->mock_bonus_amount,
            'job_application_bonus_amount' => $this->job_application_bonus_amount,
            'wider_market_job_limit' => $this->wider_market_job_limit,
            'active' => $this->active,
        ];
    }
}
