<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Verifies the `X-Razorpay-Signature` HMAC on Razorpay webhooks before any
 * handling (CLAUDE.md: verify webhook signatures; reject unsigned). Razorpay
 * signs the raw request body: HMAC_SHA256(rawBody, webhook_secret).
 */
final class VerifyRazorpayWebhookSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $secret = (string) config('services.razorpay.webhook_secret');
        $signature = $request->header('x-razorpay-signature');

        if ($secret === '' || ! is_string($signature)) {
            abort(403, 'Unsigned Razorpay webhook.');
        }

        $expected = hash_hmac('sha256', $request->getContent(), $secret);

        if (! hash_equals($expected, $signature)) {
            abort(403, 'Invalid Razorpay signature.');
        }

        return $next($request);
    }
}
