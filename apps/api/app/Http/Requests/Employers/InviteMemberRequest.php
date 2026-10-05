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
            // Ownership is granted deliberately, to someone already on the
            // team (ChangeEmployerMemberRole) — never handed to a stranger by
            // email through a routine invite form.
            'role' => ['required', Rule::in([EmployerRole::Recruiter->value, EmployerRole::HiringManager->value])],
            // A label for the owner's own pending-invites list — never the
            // invitee's real profile, which they always set themselves when
            // they accept.
            'name' => ['nullable', 'string', 'max:150'],
            'whatsapp' => ['nullable', 'string', 'max:20'],
        ];
    }
}
