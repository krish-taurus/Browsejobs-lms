<?php

declare(strict_types=1);

namespace App\Http\Requests\Taurus;

use Illuminate\Foundation\Http\FormRequest;

/** Taurus client sign-in. */
final class TaurusLoginRequest extends FormRequest
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
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'max:200'],
        ];
    }
}
