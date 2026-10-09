<?php

declare(strict_types=1);

namespace App\Support\Taurus;

use App\Models\TaurusWorkspace;
use App\Models\TaurusWorkspaceCredential;
use App\Support\AI\ClientFactory;
use App\Support\AI\ProviderResolver;

/**
 * A workspace's "Brain & voice" panel (ADR 0052). Lists only this
 * workspace's own credentials; keys appear only as a masked last-four hint
 * and are never returned.
 */
final class WorkspaceBrainStatus
{
    public function __construct(
        private readonly WorkspaceBrain $brain,
        private readonly ProviderResolver $resolver,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(TaurusWorkspace $workspace): array
    {
        $credentials = $workspace->credentials()->get()->keyBy('provider');
        $active = $this->brain->resolve($workspace);
        $voice = $this->brain->voice($workspace);
        $voiceCredential = $credentials->get(TaurusWorkspaceCredential::ELEVENLABS);

        return [
            'brain' => [
                'provider' => $workspace->brain_provider,
                'model' => $workspace->brain_model,
                'allows_platform' => WorkspaceBrain::allowsPlatform($workspace),
                'active' => $active !== null
                    ? ['provider' => $active['provider'], 'model' => $active['model'], 'source' => $active['source']]
                    : null,
            ],
            'providers' => array_map(function (string $id) use ($credentials, $workspace): array {
                /** @var TaurusWorkspaceCredential|null $credential */
                $credential = $credentials->get($id);

                return [
                    'id' => $id,
                    'label' => BrainStatus::label($id),
                    'configured' => $credential !== null && WorkspaceBrain::usable($id, $credential),
                    'mask' => $credential !== null && $credential->hasKey() ? '••••'.($credential->key_last4 ?? '') : null,
                    'model' => $credential?->model,
                    'base_url' => $credential?->base_url,
                    'default_base_url' => ClientFactory::DEFAULT_BASE_URLS[$id] ?? null,
                    'needs_base_url' => $id === 'custom',
                    // HQ may fall back to the platform's key for this provider; clients never.
                    'platform_configured' => $workspace->is_owner && $this->resolver->isConfigured($id),
                ];
            }, BrainStatus::PROVIDERS),
            'voice' => [
                'configured' => $voice !== null,
                'source' => $voice['source'] ?? null,
                'mask' => $voiceCredential !== null && $voiceCredential->hasKey() ? '••••'.($voiceCredential->key_last4 ?? '') : null,
                'voice_id' => $voiceCredential?->voice_id,
                'model' => $voiceCredential?->model,
            ],
            'ingest' => [
                'configured' => $workspace->ingest_token_hash !== null,
                'mask' => $workspace->ingest_token_last4 !== null ? '••••'.$workspace->ingest_token_last4 : null,
                'endpoint' => BrainStatus::ingestEndpoint(),
            ],
        ];
    }

    /** The last four characters worth showing for a key (short keys show nothing). */
    public static function last4(string $secret): ?string
    {
        $secret = trim($secret);

        return mb_strlen($secret) > 8 ? mb_substr($secret, -4) : null;
    }
}
