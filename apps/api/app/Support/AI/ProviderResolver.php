<?php

declare(strict_types=1);

namespace App\Support\AI;

/**
 * Resolves which configured LLM provider the AI client should actually use.
 *
 * The admin picks an "Active provider" in Settings — a specific vendor, or `auto`.
 * A provider is only usable once its API key (and, for `custom`, its base URL) is
 * set. This resolver makes the platform resilient: if the chosen provider has no
 * key, or `auto` is selected, it falls back to the first provider that IS
 * configured — so entering a Kimi key alone is enough to make every AI feature
 * work, no matter what the provider dropdown says.
 */
final class ProviderResolver
{
    /** Preference order when auto-selecting a configured provider. */
    private const ORDER = ['anthropic', 'openai', 'kimi', 'deepseek', 'grok', 'custom'];

    /**
     * The provider the AI client should build for. Honours an explicit choice
     * when it is configured; otherwise picks the first configured provider.
     * Falls back to the requested (or default) name when nothing is configured,
     * so the client still builds and fails loudly at call time as before.
     */
    public function resolve(): string
    {
        $requested = (string) config('ai.provider', 'anthropic');

        if ($requested !== 'auto' && $this->isConfigured($requested)) {
            return $requested;
        }

        return $this->firstConfigured() ?? ($requested === 'auto' ? 'anthropic' : $requested);
    }

    /** The provider actually in use, or null when no provider has a key. */
    public function active(): ?string
    {
        $requested = (string) config('ai.provider', 'anthropic');

        if ($requested !== 'auto' && $this->isConfigured($requested)) {
            return $requested;
        }

        return $this->firstConfigured();
    }

    /** The first provider (in preference order) that has a usable config. */
    public function firstConfigured(): ?string
    {
        foreach (self::ORDER as $id) {
            if ($this->isConfigured($id)) {
                return $id;
            }
        }

        return null;
    }

    /** True when the provider has an API key (and a base URL, for `custom`). */
    public function isConfigured(string $provider): bool
    {
        $config = config("ai.providers.{$provider}");
        if (! is_array($config)) {
            return false;
        }

        $hasKey = trim((string) ($config['api_key'] ?? '')) !== '';
        $hasBase = trim((string) ($config['base_url'] ?? '')) !== '';

        return $provider === 'custom' ? ($hasKey && $hasBase) : $hasKey;
    }

    /**
     * A UI-friendly status of the AI layer: the requested mode, the provider
     * actually in use, and which providers are configured. No secrets.
     *
     * @return array{mode: string, requested: string, active: string|null, providers: list<array{id: string, configured: bool}>}
     */
    public function status(): array
    {
        $requested = (string) config('ai.provider', 'anthropic');

        return [
            'mode' => $requested === 'auto' ? 'auto' : 'fixed',
            'requested' => $requested,
            'active' => $this->active(),
            'providers' => array_map(
                fn (string $id): array => ['id' => $id, 'configured' => $this->isConfigured($id)],
                self::ORDER,
            ),
        ];
    }
}
