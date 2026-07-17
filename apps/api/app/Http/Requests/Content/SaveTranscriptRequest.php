<?php

declare(strict_types=1);

namespace App\Http\Requests\Content;

use Illuminate\Foundation\Http\FormRequest;

final class SaveTranscriptRequest extends FormRequest
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
            'transcript' => ['required', 'string', 'max:'.(int) config('content.max_transcript_chars', 60000)],
            'recording_id' => ['sometimes', 'nullable', 'integer', 'exists:recordings,id'],
        ];
    }
}
