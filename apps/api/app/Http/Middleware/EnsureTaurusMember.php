<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\TaurusWorkspace;
use App\Models\TaurusWorkspaceMember;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Workspace gate for the Taurus member API (ADR 0052).
 *
 * - Not a member (or another tenant's workspace) → 404: a workspace's
 *   existence is never confirmed to an outsider.
 * - Suspended workspace → 403 `workspace_suspended`.
 * - `taurus.member:manage` additionally requires owner or admin → 403 `forbidden`.
 *
 * The resolved membership is shared with the controller as the
 * `taurus_member` request attribute.
 */
final class EnsureTaurusMember
{
    public function handle(Request $request, Closure $next, string $level = 'view'): Response
    {
        $workspace = $request->route('workspace');
        if (! $workspace instanceof TaurusWorkspace) {
            // Bindings normally resolve first; tolerate a raw id all the same.
            $workspace = TaurusWorkspace::query()->whereKey((int) $workspace)->first();
        }

        $user = $request->user();
        $member = $workspace !== null && $user !== null ? $workspace->memberFor($user) : null;

        if ($workspace === null || $member === null) {
            return response()->json(['error' => [
                'code' => 'not_found',
                'message' => 'Workspace not found.',
            ]], 404);
        }

        if (! $workspace->isActive()) {
            return response()->json(['error' => [
                'code' => 'workspace_suspended',
                'message' => 'This workspace is suspended. Contact Taurus to restore access.',
            ]], 403);
        }

        if ($level === 'manage' && ! $member->role->manages()) {
            return response()->json(['error' => [
                'code' => 'forbidden',
                'message' => 'Only a workspace owner or admin can do this.',
            ]], 403);
        }

        $request->attributes->set('taurus_member', $member);
        $request->attributes->set('taurus_workspace', $workspace);

        return $next($request);
    }

    public static function member(Request $request): TaurusWorkspaceMember
    {
        /** @var TaurusWorkspaceMember $member */
        $member = $request->attributes->get('taurus_member');

        return $member;
    }
}
