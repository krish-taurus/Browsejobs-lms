<?php

declare(strict_types=1);

namespace App\Http\Requests\Employers;

use Illuminate\Foundation\Http\FormRequest;

final class SubmitInterviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'answers' => ['required', 'array', 'min:1', 'max:20'],
            'answers.*.question_id' => ['required', 'integer'],
            'answers.*.answer' => ['required', 'string', 'max:5000'],
        ];
    }
}
