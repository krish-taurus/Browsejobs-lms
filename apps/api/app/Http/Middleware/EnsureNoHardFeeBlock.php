<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\AccessBlockLevel;
use App\Models\AccessBlock;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Server-side enforcement of the hard fee block (PRD §6.8) on course content.
 * The portal already swaps to the FeeBlockedScreen client-side; this closes the
 * gap where a hard-blocked student could still fetch lessons, labs, quizzes or
 * assignments by calling the API directly. Soft blocks are untouched — they
 * only lock live classes and recordings, enforced by DuesFeeGate.
 */
final class EnsureNoHardFeeBlock
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null) {
            $hardBlocked = AccessBlock::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', $user->tenant_id)
                ->where('user_id', $user->id)
                ->where('level', AccessBlockLevel::Hard->value)
                ->whereNull('lifted_at')
                ->exists();

            abort_if($hardBlocked, 403, 'Access is locked until your fee dues are cleared.');
        }

        return $next($request);
    }
}
