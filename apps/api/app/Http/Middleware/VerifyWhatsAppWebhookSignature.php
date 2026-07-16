<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Verifies the `X-Hub-Signature-256` HMAC on WhatsApp Cloud API webhooks before
 * handling (CLAUDE.md: verify webhook signatures; reject unsigned). Same scheme
 * as the Meta lead webhook: `sha256=` . HMAC_SHA256(rawBody, app_secret).
 */
final class VerifyWhatsAppWebhookSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $secret = (string) config('services.whatsapp.app_secret');
        $signature = $request->header('x-hub-signature-256');

        if ($secret === '' || ! is_string($signature)) {
            abort(403, 'Unsigned WhatsApp webhook.');
        }

        $expected = 'sha256='.hash_hmac('sha256', $request->getContent(), $secret);

        if (! hash_equals($expected, $signature)) {
            abort(403, 'Invalid WhatsApp signature.');
        }

        return $next($request);
    }
}
