<?php

declare(strict_types=1);

namespace App\Http\Requests\Content;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateNotesRequest extends FormRequest
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
            'notes' => ['required', 'string', 'max:'.(int) config('content.notes.max_chars', 8000)],
        ];
    }
}
