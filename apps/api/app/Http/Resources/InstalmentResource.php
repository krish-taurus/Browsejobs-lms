<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Instalment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Instalment
 */
final class InstalmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'seq' => $this->seq,
            'amount_paise' => $this->amount_paise,
            'due_on' => $this->due_on?->toDateString(),
            'status' => $this->status->value,
            'paid_at' => $this->paid_at?->toIso8601String(),
            'payment_link_url' => $this->payment_link_url,
            'razorpay_order_id' => $this->razorpay_order_id,
        ];
    }
}
