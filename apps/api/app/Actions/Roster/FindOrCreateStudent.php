<?php

declare(strict_types=1);

namespace App\Actions\Roster;

use App\Models\Scopes\TenantScope;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Resolves an existing student within a tenant by email or phone, creating a
 * new OTP-only student account if none matches. Used by silo/bulk/CSV roster
 * intake.
 */
final readonly class FindOrCreateStudent
{
    public function handle(Tenant $tenant, string $name, ?string $email = null, ?string $phone = null): User
    {
        $email = $email !== null && $email !== '' ? $email : null;
        $phone = $phone !== null && $phone !== '' ? $phone : null;

        if ($email === null && $phone === null) {
            throw ValidationException::withMessages([
                'contact' => 'A phone or email is required to add a student.',
            ]);
        }

        $existing = User::query()
            ->withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $tenant->id)
            ->where(function ($query) use ($email, $phone) {
                if ($email !== null) {
                    $query->orWhere('email', $email);
                }
                if ($phone !== null) {
                    $query->orWhere('phone', $phone);
                }
            })
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        return User::query()->create([
            'tenant_id' => $tenant->id,
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'user_type' => 'student',
        ]);
    }
}
