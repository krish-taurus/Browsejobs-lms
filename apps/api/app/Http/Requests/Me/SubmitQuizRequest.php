<?php

declare(strict_types=1);

namespace App\Http\Requests\Me;

use Illuminate\Foundation\Http\FormRequest;

final class SubmitQuizRequest extends FormRequest
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
            'answers' => ['present', 'array'],
            'answers.*' => ['integer', 'min:0'],
            'integrity' => ['sometimes', 'array'],
            'integrity.tab_blurs' => ['sometimes', 'integer', 'min:0'],
            'integrity.paste_count' => ['sometimes', 'integer', 'min:0'],
            'integrity.duration_sec' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
