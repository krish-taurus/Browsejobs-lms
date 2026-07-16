<?php

declare(strict_types=1);

namespace App\Http\Requests\Store;

use App\Enums\ProductKind;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Route middleware enforces can:manage-monetization.
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'sku' => ['required', 'string', 'max:64'],
            'name' => ['required', 'string', 'max:120'],
            'feature' => ['nullable', 'string', 'max:32'],
            'kind' => ['required', Rule::enum(ProductKind::class)],
            'price_paise' => ['required', 'integer', 'min:0'],
            'grant_amount' => ['nullable', 'integer', 'min:0'],
            'period_days' => ['nullable', 'integer', 'min:1'],
            'active' => ['nullable', 'boolean'],
        ];
    }
}
