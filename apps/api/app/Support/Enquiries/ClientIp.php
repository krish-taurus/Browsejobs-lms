<?php

declare(strict_types=1);

namespace App\Support\Enquiries;

use Illuminate\Http\Request;

/**
 * The address used for rate limits and the stored IP hash.
 *
 * A browser on the public preview talks to Next, which proxies to Laravel on
 * loopback. In that case the real client IP arrives in X-Enquiry-Client-Ip.
 * Any other peer cannot set that header, unless it also presents the proxy secret.
 */
final class ClientIp
{
    public static function resolve(Request $request): string
    {
        $peer = (string) $request->ip();
        $forwarded = trim((string) $request->headers->get('X-Enquiry-Client-Ip', ''));

        if ($forwarded !== '' && filter_var($forwarded, FILTER_VALIDATE_IP) && self::trusted($request, $peer)) {
            return $forwarded;
        }

        return $peer !== '' ? $peer : '0.0.0.0';
    }

    public static function hash(string $ip): string
    {
        return hash('sha256', $ip.'|'.(string) config('app.key'));
    }

    private static function trusted(Request $request, string $peer): bool
    {
        if (self::isLoopback($peer)) {
            return true;
        }

        $secret = (string) config('enquiry.proxy_secret');
        $given = (string) $request->headers->get('X-Enquiry-Proxy', '');

        return $secret !== '' && $given !== '' && hash_equals($secret, $given);
    }

    private static function isLoopback(string $ip): bool
    {
        return $ip === '127.0.0.1' || $ip === '::1';
    }
}
