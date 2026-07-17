<?php

declare(strict_types=1);

namespace App\Http\Requests\Quizzes;

use Illuminate\Foundation\Http\FormRequest;

final class UpsertQuizRequest extends FormRequest
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
            'instructions' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'time_limit_sec' => ['sometimes', 'integer', 'min:60', 'max:7200'],
            'pass_pct' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'shuffle' => ['sometimes', 'boolean'],
        ];
    }
}
