<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Voucher;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Voucher
 */
final class VoucherResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'type' => $this->type->value,
            'value' => $this->value,
            'max_discount_paise' => $this->max_discount_paise,
            'valid_days' => $this->valid_days,
            'valid_until' => $this->valid_until?->toDateString(),
            'course_id' => $this->course_id,
            'usage_limit' => $this->usage_limit,
            'per_user_limit' => $this->per_user_limit,
            'allow_stacking' => $this->allow_stacking,
            'is_review_reward' => $this->is_review_reward,
            'active' => $this->active,
            'issued_count' => $this->when($this->issues_count !== null, $this->issues_count),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
