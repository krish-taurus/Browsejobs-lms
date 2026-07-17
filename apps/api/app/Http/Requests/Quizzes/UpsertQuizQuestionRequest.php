<?php

declare(strict_types=1);

namespace App\Http\Requests\Quizzes;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

final class UpsertQuizQuestionRequest extends FormRequest
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
            'prompt' => ['required', 'string', 'max:1000'],
            'options' => ['required', 'array', 'min:2', 'max:6'],
            'options.*' => ['required', 'string', 'max:500'],
            'correct_index' => ['required', 'integer', 'min:0'],
            'explanation' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'position' => ['sometimes', 'integer', 'min:0'],
        ];
    }

    protected function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $options = (array) $this->input('options', []);
            if ((int) $this->input('correct_index') >= count($options)) {
                throw ValidationException::withMessages(['correct_index' => 'The correct answer must be one of the options.']);
            }
        });
    }
}
