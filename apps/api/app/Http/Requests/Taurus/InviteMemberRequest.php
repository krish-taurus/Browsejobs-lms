<?php

declare(strict_types=1);

namespace App\Http\Requests\Taurus;

use App\Enums\TaurusWorkspaceRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Invite someone into a workspace with a role. */
final class InviteMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // taurus.owner enforces the founder
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:255'],
            'role' => ['required', 'string', Rule::in(TaurusWorkspaceRole::values())],
            'name' => ['sometimes', 'nullable', 'string', 'max:120'],
        ];
    }
}
