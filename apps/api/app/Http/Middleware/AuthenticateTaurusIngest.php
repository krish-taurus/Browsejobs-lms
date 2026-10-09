<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Actions\Taurus\RotateIngestToken;
use App\Models\TaurusWorkspace;
use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Machine-to-machine auth for Taurus bots (ADR 0052).
 *
 * The bearer token *is* the workspace: its sha256 is looked up in
 * `taurus_workspaces.ingest_token_hash` (a unique index, so a token can only
 * ever match one workspace). Unknown token → 401; suspended workspace → 403.
 * The request then runs inside that workspace's tenant, and the workspace is
 * handed to the controller as the `taurus_workspace` request attribute.
 */
final class AuthenticateTaurusIngest
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = (string) $request->bearerToken();

        $workspace = $token !== ''
            ? TaurusWorkspace::withoutGlobalScopes()->where('ingest_token_hash', RotateIngestToken::hash($token))->first()
            : null;

        // hash_equals on the stored hash keeps the final comparison constant-time.
        if ($workspace === null || ! hash_equals((string) $workspace->ingest_token_hash, RotateIngestToken::hash($token))) {
            return response()->json(['error' => [
                'code' => 'unauthorized',
                'message' => 'Missing or invalid Taurus ingest token.',
            ]], 401);
        }

        if (! $workspace->isActive()) {
            return response()->json(['error' => [
                'code' => 'workspace_suspended',
                'message' => 'This Taurus workspace is suspended.',
            ]], 403);
        }

        $tenant = Tenant::query()->whereKey($workspace->tenant_id)->where('status', 'active')->first();
        if ($tenant === null) {
            return response()->json(['error' => [
                'code' => 'workspace_suspended',
                'message' => 'This Taurus workspace is unavailable.',
            ]], 403);
        }

        $request->attributes->set('taurus_workspace', $workspace);

        return app(TenantContext::class)->run($tenant, fn (): Response => $next($request));
    }
}
