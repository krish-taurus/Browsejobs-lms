<?php

declare(strict_types=1);

namespace App\Support\Taurus;

use App\Support\AI\ClientFactory;
use App\Support\AI\ProviderResolver;

/**
 * The PLATFORM brain (ADR 0052) — the employer hiring floor and HQ fallback: which LLM
 * the brain would use right now, every platform provider's readiness and the
 * voice. Never returns a secret — keys appear only as a
 * masked last-four hint.
 */
final class BrainStatus
{
    /** Providers the brain can use, in the order the console lists them. */
    public const PROVIDERS = ['anthropic', 'openai', 'gemini', 'kimi', 'deepseek', 'grok', 'groq', 'custom'];

    private const LABELS = [
        'anthropic' => 'Anthropic (Claude)',
        'openai' => 'OpenAI',
        'gemini' => 'Google Gemini',
        'kimi' => 'Kimi / Moonshot',
        'deepseek' => 'DeepSeek',
        'grok' => 'Grok / xAI',
        'groq' => 'Groq',
        'custom' => 'Custom (OpenAI-compatible)',
    ];

    public function __construct(private readonly ProviderResolver $resolver) {}

    public static function label(string $provider): string
    {
        return self::LABELS[$provider] ?? ($provider === 'elevenlabs' ? 'ElevenLabs' : $provider);
    }

    /** The brain's configured choice: `platform` or a provider id. */
    public function choice(): string
    {
        $choice = trim((string) config('taurus.brain.provider', 'platform'));

        return $choice === '' ? 'platform' : $choice;
    }

    public function modelOverride(): ?string
    {
        $model = trim((string) config('taurus.brain.model', ''));

        return $model === '' ? null : $model;
    }

    /**
     * The provider + model the brain would actually call now, or null when no
     * LLM has a key. A chosen-but-keyless provider falls back to the platform's
     * active one — and then the brain model override is dropped, since a model
     * name meant for one vendor would be rejected by another.
     *
     * `own` is true when the brain uses its own chosen provider (the call must
     * then be routed to it explicitly rather than through the platform client).
     *
     * @return array{provider: string, model: string, own: bool}|null
     */
    public function active(): ?array
    {
        $choice = $this->choice();

        if ($choice !== 'platform' && $this->resolver->isConfigured($choice)) {
            return [
                'provider' => $choice,
                'model' => $this->modelOverride() ?? (string) config("ai.providers.{$choice}.model", ''),
                'own' => true,
            ];
        }

        $platform = $this->resolver->active();
        if ($platform === null) {
            return null;
        }

        $model = $choice === 'platform' ? $this->modelOverride() : null;

        return [
            'provider' => $platform,
            'model' => $model ?? (string) config("ai.providers.{$platform}.model", ''),
            'own' => false,
        ];
    }

    /**
     * AiGateway options that send a call to the brain: its own provider when
     * it has one, its model when known. Every Taurus "ask" goes through here so
     * the founder console and the employer floor resolve the brain identically.
     *
     * @param  array{provider: string, model: string, own: bool}  $active
     * @return array{model?: string, provider?: string}
     */
    public static function gatewayOptions(array $active): array
    {
        $opts = [];
        if ($active['model'] !== '') {
            $opts['model'] = $active['model'];
        }
        if ($active['own']) {
            $opts['provider'] = $active['provider'];
        }

        return $opts;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $active = $this->active();

        return [
            'brain' => [
                'provider' => $this->choice(),
                'model' => $this->modelOverride(),
                'active' => $active !== null ? ['provider' => $active['provider'], 'model' => $active['model']] : null,
            ],
            'providers' => array_map(fn (string $id): array => $this->provider($id), self::PROVIDERS),
            'voice' => $this->voice(),
        ];
    }

    public static function ingestEndpoint(): string
    {
        return route('taurus.ingest');
    }

    /** ElevenLabs can speak only with both a key and a voice to speak in. */
    public static function voiceConfigured(): bool
    {
        return trim((string) config('services.elevenlabs.api_key', '')) !== ''
            && trim((string) config('services.elevenlabs.voice_id', '')) !== '';
    }

    /**
     * @return array<string, mixed>
     */
    private function provider(string $id): array
    {
        $config = (array) config("ai.providers.{$id}", []);
        $model = trim((string) ($config['model'] ?? ''));
        $baseUrl = trim((string) ($config['base_url'] ?? ''));

        return [
            'id' => $id,
            'label' => self::label($id),
            'configured' => $this->resolver->isConfigured($id),
            'mask' => self::mask((string) ($config['api_key'] ?? '')),
            'model' => $model !== '' ? $model : null,
            'base_url' => $baseUrl !== '' ? $baseUrl : null,
            'default_base_url' => ClientFactory::DEFAULT_BASE_URLS[$id] ?? null,
            'needs_base_url' => $id === 'custom',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function voice(): array
    {
        $voiceId = trim((string) config('services.elevenlabs.voice_id', ''));
        $model = trim((string) config('services.elevenlabs.model', ''));

        return [
            'configured' => self::voiceConfigured(),
            'mask' => self::mask((string) config('services.elevenlabs.api_key', '')),
            'voice_id' => $voiceId !== '' ? $voiceId : null,
            'model' => $model !== '' ? $model : null,
        ];
    }

    /** Same hint the settings page shows: last four characters, the rest dotted. */
    public static function mask(string $secret): ?string
    {
        $secret = trim($secret);
        if ($secret === '') {
            return null;
        }

        // A very short value would be mostly revealed by its last four.
        return mb_strlen($secret) <= 8 ? '••••' : '••••'.mb_substr($secret, -4);
    }
}
