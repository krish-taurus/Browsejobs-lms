<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\BatchType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Route middleware enforces can:manage-batches.
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'course_id' => ['required', 'integer'],
            'type' => ['required', Rule::enum(BatchType::class)],
            'number' => ['nullable', 'string', 'max:60'],
            'capacity' => ['nullable', 'integer', 'min:1'],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
        ];
    }
}
