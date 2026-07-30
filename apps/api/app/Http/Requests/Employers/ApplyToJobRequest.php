<?php

declare(strict_types=1);

namespace App\Http\Requests\Employers;

use Illuminate\Foundation\Http\FormRequest;

final class ApplyToJobRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'knockout_answers' => ['nullable', 'array', 'max:10'],
            'knockout_answers.*.question' => ['required_with:knockout_answers', 'string', 'max:300'],
            'knockout_answers.*.answer' => ['required_with:knockout_answers', 'string', 'max:500'],
        ];
    }
}
