<?php

declare(strict_types=1);

namespace App\Support\Taurus;

use App\Models\TaurusWorkspace;
use App\Models\TaurusWorkspaceCredential;
use App\Services\AI\AiClient;
use App\Support\AI\ClientFactory;
use App\Support\AI\ProviderResolver;

/**
 * Which LLM and which voice a Taurus workspace actually uses (ADR 0052).
 *
 * The isolation rule lives here, in one place: a client workspace only ever
 * uses its *own* credentials. It never falls back to the platform's keys, nor
 * to another workspace's — with no key of its own it simply has no brain (503)
 * and no voice (204, browser voice). Only the owner workspace ("Taurus HQ")
 * may use the platform: by choosing `platform`, or as a fallback when it has
 * no key of its own for the provider it chose.
 */
final class WorkspaceBrain
{
    public function __construct(private readonly ProviderResolver $resolver) {}

    /** May this workspace choose `platform` as its brain? Owner only. */
    public static function allowsPlatform(TaurusWorkspace $workspace): bool
    {
        return $workspace->is_owner;
    }

    /**
     * The brain this workspace would call now, or null when it has none.
     *
     * `opts` are ready for AiGateway::complete(): a prebuilt `client` for the
     * workspace's own key, a `provider` for an owner borrowing one platform
     * provider, or neither for the platform's active provider.
     *
     * @return array{provider: string, model: string, source: 'workspace'|'platform', opts: array{model?: string, provider?: string, client?: AiClient}}|null
     */
    public function resolve(TaurusWorkspace $workspace): ?array
    {
        $credentials = $this->credentials($workspace);
        $choice = $workspace->brain_provider !== null && trim($workspace->brain_provider) !== '' ? $workspace->brain_provider : null;
        $override = $workspace->brain_model !== null && trim($workspace->brain_model) !== '' ? trim($workspace->brain_model) : null;

        // No explicit choice: the first provider the workspace holds a key for.
        if ($choice === null) {
            foreach (BrainStatus::PROVIDERS as $id) {
                if (isset($credentials[$id]) && self::usable($id, $credentials[$id])) {
                    return $this->fromCredential($id, $credentials[$id], $override);
                }
            }

            return $workspace->is_owner ? $this->platformActive($override) : null;
        }

        if ($choice === 'platform') {
            return $workspace->is_owner ? $this->platformActive($override) : null;
        }

        if (isset($credentials[$choice]) && self::usable($choice, $credentials[$choice])) {
            return $this->fromCredential($choice, $credentials[$choice], $override);
        }

        if (! $workspace->is_owner) {
            return null; // a client never borrows a key
        }

        if ($this->resolver->isConfigured($choice)) {
            $model = $override ?? (string) config("ai.providers.{$choice}.model", '');

            return [
                'provider' => $choice,
                'model' => $model,
                'source' => 'platform',
                'opts' => array_filter(['provider' => $choice, 'model' => $model], static fn ($v): bool => $v !== ''),
            ];
        }

        // HQ chose a provider nobody has a key for: use the platform's active
        // one, without the override (a model name for one vendor means nothing to another).
        return $this->platformActive(null);
    }

    /**
     * The voice this workspace speaks with, or null (the browser voice).
     *
     * @return array{api_key: string, voice_id: string, model: string, source: 'workspace'|'platform'}|null
     */
    public function voice(TaurusWorkspace $workspace): ?array
    {
        $credential = $workspace->credential(TaurusWorkspaceCredential::ELEVENLABS);

        if ($credential !== null && $credential->hasKey() && trim((string) $credential->voice_id) !== '') {
            return [
                'api_key' => (string) $credential->api_key,
                'voice_id' => trim((string) $credential->voice_id),
                'model' => trim((string) $credential->model) !== '' ? trim((string) $credential->model) : self::defaultVoiceModel(),
                'source' => 'workspace',
            ];
        }

        if ($workspace->is_owner && BrainStatus::voiceConfigured()) {
            return [
                'api_key' => (string) config('services.elevenlabs.api_key'),
                'voice_id' => (string) config('services.elevenlabs.voice_id'),
                'model' => (string) config('services.elevenlabs.model', self::defaultVoiceModel()),
                'source' => 'platform',
            ];
        }

        return null;
    }

    /**
     * A client for one named provider as this workspace may use it — its own
     * key, or (owner only) the platform's key for that provider. Used by the
     * connection test so it proves exactly what the brain would call.
     *
     * @return array{client: AiClient, secret: string, source: 'workspace'|'platform'}|null
     */
    public function clientFor(TaurusWorkspace $workspace, string $provider): ?array
    {
        $credential = $this->credentials($workspace)[$provider] ?? null;

        if ($credential !== null && self::usable($provider, $credential)) {
            return [
                'client' => ClientFactory::fromCredentials($provider, (string) $credential->api_key, $credential->base_url, $credential->model),
                'secret' => (string) $credential->api_key,
                'source' => 'workspace',
            ];
        }

        if ($workspace->is_owner && $this->resolver->isConfigured($provider)) {
            return [
                'client' => ClientFactory::for($provider),
                'secret' => (string) config("ai.providers.{$provider}.api_key"),
                'source' => 'platform',
            ];
        }

        return null;
    }

    /** A credential is usable once it has a key — and, for `custom`, an endpoint. */
    public static function usable(string $provider, TaurusWorkspaceCredential $credential): bool
    {
        if (! $credential->hasKey()) {
            return false;
        }

        return $provider !== 'custom' || trim((string) $credential->base_url) !== '';
    }

    public static function defaultVoiceModel(): string
    {
        return 'eleven_turbo_v2_5';
    }

    /**
     * @return array<string, TaurusWorkspaceCredential>
     */
    private function credentials(TaurusWorkspace $workspace): array
    {
        return $workspace->credentials()->get()->keyBy('provider')->all();
    }

    /**
     * @return array{provider: string, model: string, source: 'workspace', opts: array{model: string, client: AiClient}}
     */
    private function fromCredential(string $provider, TaurusWorkspaceCredential $credential, ?string $override): array
    {
        $model = $override
            ?? (trim((string) $credential->model) !== '' ? trim((string) $credential->model) : ClientFactory::defaultModel($provider));

        return [
            'provider' => $provider,
            'model' => $model,
            'source' => 'workspace',
            'opts' => [
                'model' => $model,
                'client' => ClientFactory::fromCredentials($provider, (string) $credential->api_key, $credential->base_url, $model),
            ],
        ];
    }

    /**
     * @return array{provider: string, model: string, source: 'platform', opts: array{model?: string}}|null
     */
    private function platformActive(?string $override): ?array
    {
        $provider = $this->resolver->active();
        if ($provider === null) {
            return null;
        }

        $model = $override ?? (string) config("ai.providers.{$provider}.model", '');

        return [
            'provider' => $provider,
            'model' => $model,
            'source' => 'platform',
            'opts' => $model !== '' ? ['model' => $model] : [],
        ];
    }
}
