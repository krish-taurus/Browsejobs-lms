<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Verifies the `x-zm-signature` HMAC on Zoom webhooks before any handling
 * (CLAUDE.md: verify webhook signatures; reject unsigned). Signature is
 * v0=HMAC_SHA256("v0:{timestamp}:{rawBody}", webhook_secret).
 */
final class VerifyZoomWebhookSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $secret = (string) config('services.zoom.webhook_secret');
        $timestamp = $request->header('x-zm-request-timestamp');
        $signature = $request->header('x-zm-signature');

        if ($secret === '' || ! is_string($timestamp) || ! is_string($signature)) {
            abort(403, 'Unsigned Zoom webhook.');
        }

        $expected = 'v0='.hash_hmac('sha256', "v0:{$timestamp}:{$request->getContent()}", $secret);

        if (! hash_equals($expected, $signature)) {
            abort(403, 'Invalid Zoom signature.');
        }

        return $next($request);
    }
}
