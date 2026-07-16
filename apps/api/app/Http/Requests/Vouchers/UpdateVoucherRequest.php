<?php

declare(strict_types=1);

namespace App\Http\Requests\Vouchers;

use App\Enums\VoucherType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateVoucherRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Route middleware enforces can:manage-vouchers.
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:64'],
            'type' => ['sometimes', 'required', Rule::enum(VoucherType::class)],
            'value' => ['sometimes', 'required', 'integer', 'min:1'],
            'max_discount_paise' => ['nullable', 'integer', 'min:0'],
            'valid_days' => ['nullable', 'integer', 'min:1'],
            'valid_until' => ['nullable', 'date'],
            'course_id' => ['nullable', 'integer'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'per_user_limit' => ['sometimes', 'integer', 'min:1'],
            'allow_stacking' => ['sometimes', 'boolean'],
            'is_review_reward' => ['sometimes', 'boolean'],
            'active' => ['sometimes', 'boolean'],
        ];
    }
}
