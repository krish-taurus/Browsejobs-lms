<?php

declare(strict_types=1);

namespace App\Http\Requests\Content;

use Illuminate\Foundation\Http\FormRequest;

final class UploadTranscriptRequest extends FormRequest
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
        $mimes = implode(',', (array) config('content.upload.mimes'));
        $maxKb = (int) config('content.upload.max_mb', 5) * 1024;

        return [
            'file' => ['required', 'file', "mimes:{$mimes}", "max:{$maxKb}"],
            'recording_id' => ['sometimes', 'nullable', 'integer', 'exists:recordings,id'],
        ];
    }
}
