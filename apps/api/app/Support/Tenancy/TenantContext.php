<?php

declare(strict_types=1);

namespace App\Support\Tenancy;

use App\Models\Tenant;
use Closure;

/**
 * Holds the tenant resolved for the current request (or job).
 *
 * Registered as a singleton in the container so the global scope, the
 * BelongsToTenant trait, and the resolution middleware all read/write the
 * same instance. When no tenant is set the scope is a no-op — this is what
 * lets the resolve-by-user middleware look a user up before a tenant is known.
 */
final class TenantContext
{
    private ?Tenant $tenant = null;

    public function set(Tenant $tenant): void
    {
        $this->tenant = $tenant;
    }

    public function get(): ?Tenant
    {
        return $this->tenant;
    }

    public function id(): ?int
    {
        return $this->tenant?->id;
    }

    public function has(): bool
    {
        return $this->tenant !== null;
    }

    public function forget(): void
    {
        $this->tenant = null;
    }

    /**
     * Run a callback with the given tenant active, restoring the previous
     * tenant afterwards. The canonical helper for scoping a block of work
     * (and the basis of the reusable cross-tenant test pattern).
     *
     * @template TReturn
     *
     * @param  Closure(): TReturn  $callback
     * @return TReturn
     */
    public function run(Tenant $tenant, Closure $callback)
    {
        $previous = $this->tenant;
        $this->tenant = $tenant;

        try {
            return $callback();
        } finally {
            $this->tenant = $previous;
        }
    }
}
