<?php

declare(strict_types=1);

namespace App\Http\Requests\Payments;

use App\Enums\FeePlanType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class PreviewScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Route middleware enforces can:manage-fees.
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(FeePlanType::class)],
            'emi_count' => ['nullable', 'integer', 'in:1,2,3'],
            'discount_paise' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
