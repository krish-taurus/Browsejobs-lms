<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the active tenant from the request host — used on public routes
 * (landing, courses, masterclass pages) where there is no authenticated user.
 * An unknown host yields a 404 rather than silently leaking a default tenant.
 */
final class ResolveTenantByDomain
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = Tenant::query()
            ->where('domain', $request->getHost())
            ->where('status', 'active')
            ->first();

        // Fallback for single-tenant/local hosts that carry no domain mapping.
        if ($tenant === null && is_string($default = config('tenancy.default_slug'))) {
            $tenant = Tenant::query()
                ->where('slug', $default)
                ->where('status', 'active')
                ->first();
        }

        if ($tenant === null) {
            abort(404, 'Unknown tenant domain.');
        }

        app(TenantContext::class)->set($tenant);

        return $next($request);
    }
}
