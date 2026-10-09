<?php

declare(strict_types=1);

namespace App\Http\Requests\Taurus;

use App\Models\TaurusWorkspace;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Rename, suspend or reactivate a workspace. */
final class UpdateWorkspaceRequest extends FormRequest
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
            'status' => ['sometimes', 'string', Rule::in([TaurusWorkspace::STATUS_ACTIVE, TaurusWorkspace::STATUS_SUSPENDED])],
            'name' => ['sometimes', 'string', 'max:120'],
        ];
    }
}
