<?php

declare(strict_types=1);

namespace App\Http\Requests\Taurus;

use Illuminate\Foundation\Http\FormRequest;

/** Approve or reject a parked task, with an optional note the bot can read. */
final class DecideTaskRequest extends FormRequest
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
            'note' => ['sometimes', 'nullable', 'string', 'max:500'],
        ];
    }
}
