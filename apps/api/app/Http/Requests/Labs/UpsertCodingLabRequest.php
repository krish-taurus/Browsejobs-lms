<?php

declare(strict_types=1);

namespace App\Http\Requests\Labs;

use App\Enums\CodeLanguage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpsertCodingLabRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Route middleware enforces can:manage-curriculum.
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'language' => ['required', Rule::enum(CodeLanguage::class)],
            'instructions' => ['nullable', 'string', 'max:5000'],
            'starter_code' => ['nullable', 'string', 'max:100000'],
            'test_cases' => ['nullable', 'array', 'max:50'],
            'test_cases.*.stdin' => ['nullable', 'string', 'max:10000'],
            'test_cases.*.expected_stdout' => ['required', 'string', 'max:10000'],
            'time_limit_ms' => ['nullable', 'integer', 'min:500', 'max:30000'],
        ];
    }
}
