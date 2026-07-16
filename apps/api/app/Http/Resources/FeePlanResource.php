<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\FeePlan;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin FeePlan
 */
final class FeePlanResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'status' => $this->status->value,
            'total_paise' => $this->total_paise,
            'discount_paise' => $this->discount_paise,
            'net_payable_paise' => $this->netPayablePaise(),
            'gst_rate_bps' => $this->gst_rate_bps,
            'currency' => $this->currency,
            'created_at' => $this->created_at?->toIso8601String(),
            'student' => $this->whenLoaded('student', fn () => $this->student === null ? null : [
                'id' => $this->student->id,
                'name' => $this->student->name,
                'phone' => $this->student->phone,
                'email' => $this->student->email,
            ]),
            'batch' => $this->whenLoaded('batch', fn () => $this->batch === null ? null : [
                'id' => $this->batch->id,
                'number' => $this->batch->number,
                'course' => $this->batch->relationLoaded('course') && $this->batch->course !== null
                    ? ['id' => $this->batch->course->id, 'name' => $this->batch->course->name]
                    : null,
            ]),
            'instalments' => InstalmentResource::collection($this->whenLoaded('instalments')),
            'ledger' => LedgerEntryResource::collection($this->whenLoaded('ledgerEntries')),
            'receipts' => ReceiptResource::collection($this->whenLoaded('receipts')),
        ];
    }
}
