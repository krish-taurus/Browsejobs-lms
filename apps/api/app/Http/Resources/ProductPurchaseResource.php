<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\ProductPurchase;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * @mixin ProductPurchase
 */
final class ProductPurchaseResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sku' => $this->sku,
            'feature' => $this->feature,
            'kind' => $this->kind->value,
            'amount_paise' => $this->amount_paise,
            'total_paise' => $this->total_paise,
            'status' => $this->status->value,
            'receipt_number' => $this->receipt_number,
            'receipt_url' => $this->receiptUrl(),
            'captured_at' => $this->captured_at?->toIso8601String(),
            'refunded_at' => $this->refunded_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'student' => $this->whenLoaded('student', fn () => $this->student === null ? null : [
                'id' => $this->student->id,
                'name' => $this->student->name,
            ]),
        ];
    }

    private function receiptUrl(): ?string
    {
        if ($this->receipt_path === null) {
            return null;
        }

        try {
            return Storage::disk('s3')->temporaryUrl($this->receipt_path, now()->addHour());
        } catch (Throwable) {
            return null;
        }
    }
}
