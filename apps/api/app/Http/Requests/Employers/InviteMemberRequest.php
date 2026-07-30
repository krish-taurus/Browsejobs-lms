<?php

declare(strict_types=1);

namespace App\Http\Requests\Employers;

use App\Enums\EmployerRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class InviteMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Controller enforces owner-only via ResolvesMembership.
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:255'],
            'role' => ['required', Rule::enum(EmployerRole::class)],
        ];
    }
}
