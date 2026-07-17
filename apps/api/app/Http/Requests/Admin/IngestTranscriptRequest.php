<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Transcript upload (PRD §6.6). The consent checkbox is a hard gate — the
 * uploader confirms they have the right to share the recording/transcript,
 * and the confirmation is stamped + audit-logged downstream.
 */
final class IngestTranscriptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Route middleware enforces can:manage-placements.
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'source' => ['required', Rule::in(['parakeet', 'text', 'file', 'audio', 'zip', 'debrief'])],
            'consent' => ['accepted'],
            'text' => ['required_without:file', 'nullable', 'string', 'max:200000'],
            'file' => ['required_without:text', 'nullable', 'file', 'max:102400'], // 100 MB — audio/video
            'course_id' => ['nullable', 'integer'],
            'role_title' => ['nullable', 'string', 'max:190'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'consent.accepted' => 'You must confirm you have the right to share this transcript.',
        ];
    }
}
