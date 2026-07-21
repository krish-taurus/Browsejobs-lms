<?php

declare(strict_types=1);

namespace App\Http\Requests\Team;

use App\Http\Requests\Team\Concerns\AssignableRoles;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreStaffRequest extends FormRequest
{
    use AssignableRoles;

    public function authorize(): bool
    {
        return $this->user()?->can('manage-users') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $tenantId = app(TenantContext::class)->id();

        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => [
                'required', 'email', 'max:191',
                Rule::unique(User::class, 'email')
                    ->where(fn ($q) => $q->where('tenant_id', $tenantId)),
            ],
            'phone' => ['nullable', 'string', 'max:20'],
            'password' => ['required', 'string', 'min:10', 'max:200'],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['string', Rule::in($this->assignableRoles())],
        ];
    }
}
