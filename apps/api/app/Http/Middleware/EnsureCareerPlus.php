<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Entitlements\EntitlementService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates the P4.6b job-probability boosters behind an active Career+ subscription
 * (PRD §6.20). Career+ is a non-countable entitlement keyed 'career_plus'; a 403 with a
 * clear code lets the client show the upsell rather than a bare error.
 */
final class EnsureCareerPlus
{
    public function __construct(private readonly EntitlementService $entitlements) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ! $this->entitlements->hasEntitlement($user, 'career_plus')) {
            abort(403, 'Career+ subscription required.');
        }

        return $next($request);
    }
}
