<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Verifies the shared secret on voice-provider webhooks before any handling
 * (CLAUDE.md: verify webhook signatures; reject unsigned). Vapi sends the
 * configured server secret verbatim in `x-vapi-secret`; an HMAC-signing
 * provider (Retell) can swap in behind the same alias.
 */
final class VerifyVoiceWebhookSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $secret = (string) config('services.vapi.webhook_secret');
        $header = $request->header('x-vapi-secret');

        if ($secret === '' || ! is_string($header) || ! hash_equals($secret, $header)) {
            abort(403, 'Unsigned voice webhook.');
        }

        return $next($request);
    }
}
