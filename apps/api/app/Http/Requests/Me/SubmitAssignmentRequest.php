<?php

declare(strict_types=1);

namespace App\Http\Requests\Me;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

final class SubmitAssignmentRequest extends FormRequest
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
        $mimes = implode(',', (array) config('assignments.upload.mimes'));
        $maxKb = (int) config('assignments.upload.max_mb', 20) * 1024;

        return [
            'body' => ['nullable', 'string', 'max:20000'],
            'link' => ['nullable', 'url', 'max:2000'],
            'file' => ['nullable', 'file', "mimes:{$mimes}", "max:{$maxKb}"],
        ];
    }

    protected function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (blank($this->input('body')) && blank($this->input('link')) && ! $this->hasFile('file')) {
                throw ValidationException::withMessages(['submission' => 'Add a written response, a link, or a file.']);
            }
        });
    }
}
