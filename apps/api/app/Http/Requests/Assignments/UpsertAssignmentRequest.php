<?php

declare(strict_types=1);

namespace App\Http\Requests\Assignments;

use Illuminate\Foundation\Http\FormRequest;

final class UpsertAssignmentRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:200'],
            'instructions' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'rubric' => ['required', 'array', 'min:1', 'max:20'],
            'rubric.*.criterion' => ['required', 'string', 'max:200'],
            'rubric.*.max_points' => ['required', 'integer', 'min:1', 'max:1000'],
            'allow_link' => ['sometimes', 'boolean'],
            'allow_file' => ['sometimes', 'boolean'],
        ];
    }
}
