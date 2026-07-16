<?php

declare(strict_types=1);

namespace App\Http\Requests\Tutor;

use Illuminate\Foundation\Http\FormRequest;

final class AskTutorRequest extends FormRequest
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
            'question' => ['required', 'string', 'min:3', 'max:2000'],
            'conversation_id' => ['sometimes', 'nullable', 'integer'],
        ];
    }
}
