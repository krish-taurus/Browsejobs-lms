<?php

declare(strict_types=1);

namespace App\Http\Requests\Employers;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * Claiming an onboarding invite (PRD-E F1). Public by design — the person
 * has no account credential yet, which is exactly what this sets.
 */
final class ClaimInviteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'token' => ['required', 'string', 'size:64'],
            // A workspace owner can see every applicant's contact details and
            // move people through a hiring pipeline, so the first password on
            // that account is not the place to accept "password1".
            'password' => ['required', 'confirmed', Password::min(12)],
        ];
    }
}
