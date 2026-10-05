<?php

declare(strict_types=1);

namespace App\Actions\Chat;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Fetches a small amount of metadata (title, description, image, site name)
 * for a URL pasted into batch chat, so the message can show a rich preview
 * card — the same thing WhatsApp already does for the exact same links,
 * which is what this is matching (Sept 2026). Best-effort and cached: a
 * slow or broken source URL never breaks the chat, and a Zoom link pasted
 * by staff and read by 300 students is only ever fetched once.
 */
final class FetchLinkPreview
{
    private const CACHE_TTL_DAYS = 7;

    private const MAX_BYTES = 262144; // 256KB is plenty to reach a page's <head>

    /**
     * @return array{title: ?string, description: ?string, image: ?string, site_name: ?string}|null
     */
    public function handle(string $url): ?array
    {
        $key = 'link_preview:'.md5($url);

        return Cache::remember($key, now()->addDays(self::CACHE_TTL_DAYS), fn () => $this->fetch($url));
    }

    /**
     * @return array{title: ?string, description: ?string, image: ?string, site_name: ?string}|null
     */
    private function fetch(string $url): ?array
    {
        if (! $this->isSafeUrl($url)) {
            return null;
        }

        try {
            $response = Http::timeout(5)
                ->withUserAgent('Mozilla/5.0 (compatible; BrowseJobsBot/1.0; +https://browsejobs.ai)')
                ->withOptions(['allow_redirects' => ['max' => 3]])
                ->get($url);
        } catch (Throwable) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $contentType = (string) $response->header('Content-Type');
        if ($contentType !== '' && ! str_contains($contentType, 'text/html')) {
            return null;
        }

        return $this->parse(substr($response->body(), 0, self::MAX_BYTES));
    }

    /**
     * @return array{title: ?string, description: ?string, image: ?string, site_name: ?string}
     */
    private function parse(string $html): array
    {
        $title = $this->metaContent($html, 'og:title') ?? $this->tag($html, 'title');
        $description = $this->metaContent($html, 'og:description') ?? $this->metaContent($html, 'description');
        $image = $this->metaContent($html, 'og:image');
        $siteName = $this->metaContent($html, 'og:site_name');

        return [
            'title' => $title !== null ? trim(html_entity_decode($title, ENT_QUOTES)) : null,
            'description' => $description !== null ? trim(html_entity_decode($description, ENT_QUOTES)) : null,
            'image' => $image,
            'site_name' => $siteName !== null ? trim(html_entity_decode($siteName, ENT_QUOTES)) : null,
        ];
    }

    /** Matches either attribute order — property/name before content, or after. */
    private function metaContent(string $html, string $property): ?string
    {
        $quoted = preg_quote($property, '/');

        if (preg_match('/<meta[^>]+(?:property|name)=["\']'.$quoted.'["\'][^>]+content=["\']([^"\']*)["\']/i', $html, $m)) {
            return $m[1];
        }
        if (preg_match('/<meta[^>]+content=["\']([^"\']*)["\'][^>]+(?:property|name)=["\']'.$quoted.'["\']/i', $html, $m)) {
            return $m[1];
        }

        return null;
    }

    private function tag(string $html, string $tag): ?string
    {
        if (preg_match('#<'.$tag.'[^>]*>([^<]*)</'.$tag.'>#i', $html, $m)) {
            return trim($m[1]);
        }

        return null;
    }

    /**
     * Blocks the classic SSRF targets: a non-http(s) scheme, or a hostname
     * that resolves to a private/loopback/link-local address — a Zoom link
     * pasted here should fetch zoom.us, never the server's own metadata
     * endpoint or another service on the private network.
     */
    private function isSafeUrl(string $url): bool
    {
        $parts = parse_url($url);
        if ($parts === false || ! isset($parts['scheme'], $parts['host'])) {
            return false;
        }

        if (! in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
            return false;
        }

        $host = $parts['host'];
        $ips = [];

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            $ips[] = $host;
        } else {
            $records = @dns_get_record($host, DNS_A + DNS_AAAA);
            if ($records === false) {
                return false;
            }
            foreach ($records as $record) {
                $ips[] = $record['ip'] ?? $record['ipv6'] ?? null;
            }
        }

        $ips = array_values(array_filter($ips));
        if ($ips === []) {
            return false;
        }

        foreach ($ips as $ip) {
            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return false;
            }
        }

        return true;
    }
}
