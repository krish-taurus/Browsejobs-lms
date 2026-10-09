<?php

declare(strict_types=1);

namespace App\Support\AI;

use App\Services\AI\AiClient;
use App\Services\AI\AnthropicClient;
use App\Services\AI\GeminiClient;
use App\Services\AI\OpenAiCompatibleClient;
use RuntimeException;

/**
 * Builds an AiClient transport for one named provider from config/ai.php.
 *
 * The container binding of AiClient resolves the platform's *active* provider;
 * this is for callers that need a specific one (the Taurus brain, a provider
 * connection test) without changing what every other feature uses.
 */
final class ClientFactory
{
    /**
     * The vendors' public endpoints. Hard-coded rather than read from config
     * so a workspace never inherits a base URL the platform pointed elsewhere
     * (a proxy, a self-hosted gateway).
     */
    public const DEFAULT_BASE_URLS = [
        'anthropic' => 'https://api.anthropic.com/v1',
        'openai' => 'https://api.openai.com/v1',
        'gemini' => 'https://generativelanguage.googleapis.com/v1beta',
        'kimi' => 'https://api.moonshot.ai/v1',
        'deepseek' => 'https://api.deepseek.com/v1',
        'grok' => 'https://api.x.ai/v1',
        'groq' => 'https://api.groq.com/openai/v1',
        'custom' => null,
    ];

    /**
     * A client for a provider that must be configured.
     *
     * @throws ProviderNotConfigured when the provider has no key (or base URL for custom)
     */
    public static function for(string $providerId): AiClient
    {
        if (! app(ProviderResolver::class)->isConfigured($providerId)) {
            throw ProviderNotConfigured::for($providerId);
        }

        return self::build($providerId);
    }

    /**
     * A client for a provider regardless of whether its key is set — the
     * container binding uses this so an unconfigured platform still builds and
     * fails loudly at call time, as it always has.
     */
    public static function build(string $providerId): AiClient
    {
        $config = config("ai.providers.{$providerId}");

        if (! is_array($config)) {
            throw new RuntimeException("Unknown AI provider [{$providerId}] — see config/ai.php.");
        }

        return self::make($providerId, $config);
    }

    /**
     * A client built from someone else's credentials — a Taurus workspace's
     * own key — using only the provider's *shape* from config/ai.php (driver,
     * API version, default endpoint and model). The platform's key for that
     * provider is never read, so a workspace can't borrow it by accident.
     *
     * @throws ProviderNotConfigured when the key (or a custom base URL) is missing
     */
    public static function fromCredentials(string $providerId, string $apiKey, ?string $baseUrl = null, ?string $model = null): AiClient
    {
        $shape = config("ai.providers.{$providerId}");

        if (! is_array($shape)) {
            throw new RuntimeException("Unknown AI provider [{$providerId}] — see config/ai.php.");
        }

        $apiKey = trim($apiKey);
        $baseUrl = trim((string) $baseUrl) !== '' ? trim((string) $baseUrl) : self::defaultBaseUrl($providerId);
        if ($apiKey === '' || $baseUrl === null) {
            throw ProviderNotConfigured::for($providerId);
        }

        $config = [
            ...$shape,
            'api_key' => $apiKey,
            'base_url' => $baseUrl,
            'model' => trim((string) $model) !== '' ? trim((string) $model) : self::defaultModel($providerId),
        ];

        return self::make($providerId, $config);
    }

    /** The shipped default model for a provider (never a workspace's override). */
    public static function defaultModel(string $providerId): string
    {
        return (string) config("ai.providers.{$providerId}.model", '');
    }

    /**
     * The provider's shipped endpoint. `custom` has none — a workspace must
     * supply its own, rather than inherit whatever the platform points at.
     */
    public static function defaultBaseUrl(string $providerId): ?string
    {
        return self::DEFAULT_BASE_URLS[$providerId] ?? null;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private static function make(string $providerId, array $config): AiClient
    {
        return match ($config['driver'] ?? null) {
            'anthropic' => new AnthropicClient($config),
            'openai_compatible' => new OpenAiCompatibleClient($config),
            'gemini' => new GeminiClient($config),
            default => throw new RuntimeException("Unknown AI driver for provider [{$providerId}]."),
        };
    }
}
