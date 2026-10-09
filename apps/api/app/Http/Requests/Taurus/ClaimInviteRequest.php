<?php

declare(strict_types=1);

namespace App\Http\Requests\Taurus;

use Illuminate\Foundation\Http\FormRequest;

/** A client accepts a Taurus workspace invite and sets (or proves) a password. */
final class ClaimInviteRequest extends FormRequest
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
            'token' => ['required', 'string', 'max:128'],
            'name' => ['required', 'string', 'max:120'],
            'password' => ['required', 'string', 'min:10', 'max:200', 'confirmed'],
        ];
    }
}
