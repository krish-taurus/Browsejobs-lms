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
        // Next and Nginx share the VPS. The proxy connects to loopback, or to
        // a private address, and does not need ENQUIRY_PROXY_SECRET. A public
        // peer cannot set the visitor IP unless it also has the secret.
        if (self::isLoopback($peer) || self::isPrivate($peer)) {
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

    private static function isPrivate(string $ip): bool
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
            $long = ip2long($ip);
            if ($long === false) {
                return false;
            }

            foreach ([['10.0.0.0', '10.255.255.255'], ['172.16.0.0', '172.31.255.255'], ['192.168.0.0', '192.168.255.255']] as [$start, $end]) {
                if ($long >= ip2long($start) && $long <= ip2long($end)) {
                    return true;
                }
            }

            return false;
        }

        $packed = inet_pton($ip);
        if ($packed === false || strlen($packed) !== 16) {
            return false;
        }

        // fc00::/7 unique local addresses.
        return (ord($packed[0]) & 0xFE) === 0xFC;
    }
}
