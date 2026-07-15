<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\Role;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Role and permission helpers for the User model. Permission checks resolve
 * through the user's roles; the Gate::before hook in AppServiceProvider turns
 * these into `$user->can('permission-slug')` calls.
 */
trait HasRoles
{
    /**
     * @return BelongsToMany<Role, $this>
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }

    public function hasRole(string ...$slugs): bool
    {
        return $this->roles()->whereIn('slug', $slugs)->exists();
    }

    public function hasPermission(string $slug): bool
    {
        return $this->roles()
            ->whereHas('permissions', fn ($query) => $query->where('slug', $slug))
            ->exists();
    }

    public function isStaff(): bool
    {
        return $this->roles()->where('is_staff', true)->exists();
    }

    public function assignRole(Role|string $role): void
    {
        $this->roles()->syncWithoutDetaching([$this->resolveRole($role)->id]);
    }

    public function removeRole(Role|string $role): void
    {
        $this->roles()->detach($this->resolveRole($role)->id);
    }

    private function resolveRole(Role|string $role): Role
    {
        return $role instanceof Role
            ? $role
            : Role::query()->where('slug', $role)->firstOrFail();
    }
}
