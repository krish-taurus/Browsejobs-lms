<?php

declare(strict_types=1);

namespace App\Support\JobFeed;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Real Apify client (ADR 0048): run-sync-get-dataset-items — one call runs the
 * actor and returns its dataset. Scraper actors take minutes, so this only ever
 * runs inside the queued feed sync, never in a request. No token (neither the
 * platform token from admin Settings / APIFY_TOKEN nor a per-source override)
 * means a safe no-op; failures log and return [] so one dead actor degrades to
 * "no new items", never a crashed sync.
 */
final readonly class HttpApifyTransport implements ApifyTransport
{
    /** Actor runs are slow — allow up to five minutes before giving up. */
    private const TIMEOUT_SECONDS = 300;

    /**
     * @param  array{token: string, base_url: string}  $config
     */
    public function __construct(private array $config) {}

    public function run(string $actorId, array $input, ?string $token = null): array
    {
        $effectiveToken = trim((string) ($token ?? '')) !== '' ? trim((string) $token) : trim($this->config['token']);
        if ($effectiveToken === '') {
            return []; // No token configured anywhere — sync to nothing, never error.
        }

        try {
            // Apify addresses actors as user~actor-name in URLs.
            $actor = str_replace('/', '~', $actorId);
            $base = rtrim($this->config['base_url'], '/');

            $response = Http::timeout(self::TIMEOUT_SECONDS)
                ->withQueryParameters(['token' => $effectiveToken, 'format' => 'json'])
                ->asJson()
                ->post("{$base}/v2/acts/{$actor}/run-sync-get-dataset-items", $input);

            if (! $response->successful()) {
                Log::warning('Apify actor run failed', ['actor' => $actorId, 'status' => $response->status()]);

                return [];
            }

            $items = $response->json();

            return is_array($items) ? array_values(array_filter($items, 'is_array')) : [];
        } catch (Throwable $e) {
            Log::warning('Apify actor run errored', ['actor' => $actorId, 'error' => $e->getMessage()]);

            return [];
        }
    }
}
