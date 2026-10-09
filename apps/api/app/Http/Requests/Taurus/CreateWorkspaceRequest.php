<?php

declare(strict_types=1);

namespace App\Http\Requests\Taurus;

use Illuminate\Foundation\Http\FormRequest;

/** The founder opens a client workspace and names its first owner. */
final class CreateWorkspaceRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:120'],
            'owner_email' => ['required', 'email', 'max:255'],
            'owner_name' => ['sometimes', 'nullable', 'string', 'max:120'],
        ];
    }
}
