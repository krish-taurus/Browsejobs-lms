<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\VoucherIssue;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin VoucherIssue
 */
final class VoucherIssueResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'status' => $this->status->value,
            'discount_paise' => $this->discount_paise,
            'issued_at' => $this->issued_at?->toIso8601String(),
            'expires_at' => $this->expires_at?->toIso8601String(),
            'applied_at' => $this->applied_at?->toIso8601String(),
            'voucher' => $this->whenLoaded('voucher', fn () => $this->voucher === null ? null : [
                'id' => $this->voucher->id,
                'name' => $this->voucher->name,
                'type' => $this->voucher->type->value,
                'value' => $this->voucher->value,
            ]),
        ];
    }
}
