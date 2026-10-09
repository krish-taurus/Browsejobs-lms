<?php

declare(strict_types=1);

namespace App\Http\Requests\Taurus;

use Illuminate\Foundation\Http\FormRequest;

/** One line for Taurus to say out loud. */
final class SpeakRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'text' => ['required', 'string', 'max:600'],
        ];
    }
}
