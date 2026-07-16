<?php

declare(strict_types=1);

namespace App\Http\Requests\Support;

use Illuminate\Foundation\Http\FormRequest;

final class ReplyTicketRequest extends FormRequest
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
        $mimes = implode(',', (array) config('support.upload.mimes', ['jpg', 'jpeg', 'png', 'pdf']));
        $maxKb = (int) config('support.upload.max_mb', 10) * 1024;

        return [
            'body' => ['required', 'string', 'min:1', 'max:5000'],
            'is_internal' => ['nullable', 'boolean'],
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => ['file', "mimes:{$mimes}", "max:{$maxKb}"],
        ];
    }
}
