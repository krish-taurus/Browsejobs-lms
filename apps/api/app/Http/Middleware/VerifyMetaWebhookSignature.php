<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Verifies the `X-Hub-Signature-256` HMAC on Meta Lead Ads webhooks before any
 * handling (CLAUDE.md: verify webhook signatures; reject unsigned). Meta signs
 * the raw request body: `sha256=` . HMAC_SHA256(rawBody, app_secret). Applied to
 * the POST delivery route only — the GET subscription handshake carries no body.
 */
final class VerifyMetaWebhookSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $secret = (string) config('services.meta.app_secret');
        $signature = $request->header('x-hub-signature-256');

        if ($secret === '' || ! is_string($signature)) {
            abort(403, 'Unsigned Meta webhook.');
        }

        $expected = 'sha256='.hash_hmac('sha256', $request->getContent(), $secret);

        if (! hash_equals($expected, $signature)) {
            abort(403, 'Invalid Meta signature.');
        }

        return $next($request);
    }
}
