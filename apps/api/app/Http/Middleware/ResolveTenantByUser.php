<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the active tenant from the authenticated user — used on portal
 * routes (/admin, /trainer, /student). The user lookup itself runs before a
 * tenant is set, so the global scope is a no-op at that point (see TenantScope).
 */
final class ResolveTenantByUser
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || empty($user->tenant_id)) {
            abort(403, 'No tenant associated with this account.');
        }

        $tenant = Tenant::query()
            ->whereKey($user->tenant_id)
            ->where('status', 'active')
            ->first();

        if ($tenant === null) {
            abort(403, 'Tenant is unavailable.');
        }

        app(TenantContext::class)->set($tenant);

        return $next($request);
    }
}
