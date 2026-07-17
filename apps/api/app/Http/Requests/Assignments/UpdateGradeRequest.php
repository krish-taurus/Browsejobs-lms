<?php

declare(strict_types=1);

namespace App\Http\Requests\Assignments;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateGradeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'feedback' => ['nullable', 'string', 'max:5000'],
            'criteria' => ['present', 'array'],
            'criteria.*.criterion' => ['required', 'string', 'max:200'],
            'criteria.*.score' => ['required', 'integer', 'min:0'],
            'criteria.*.note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
