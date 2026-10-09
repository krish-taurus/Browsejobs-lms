<?php

declare(strict_types=1);

namespace App\Actions\Taurus;

use App\Models\TaurusWorkspace;
use App\Models\TaurusWorkspaceCredential;
use App\Services\AI\AiClient;
use App\Services\AI\AiMessage;
use App\Support\AI\ClientFactory;
use App\Support\AI\ProviderResolver;
use App\Support\Taurus\BrainStatus;
use App\Support\Taurus\WorkspaceBrain;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * One live call to prove a saved key works (ADR 0052): a one-word completion
 * for an LLM, a voice listing for ElevenLabs. Failures are reported, never
 * thrown, and the message never contains the key — only the HTTP status.
 */
final class TestBrainConnection
{
    public function __construct(
        private readonly ProviderResolver $resolver,
        private readonly WorkspaceBrain $brain,
    ) {}

    /**
     * The platform's own keys (the employer hiring-floor brain).
     *
     * @return array{ok: bool, message: string, latency_ms: int}
     */
    public function handle(string $target): array
    {
        if ($target === 'elevenlabs') {
            return $this->voice(
                trim((string) config('services.elevenlabs.api_key', '')),
                trim((string) config('services.elevenlabs.voice_id', '')) !== '',
            );
        }

        $label = BrainStatus::label($target);

        if (! $this->resolver->isConfigured($target)) {
            return $this->result(false, $this->needs($target), 0);
        }

        return $this->llm($label, fn (): AiClient => ClientFactory::for($target), (string) config("ai.providers.{$target}.api_key"));
    }

    /**
     * Exactly what one workspace would call: its own key, or — HQ only — the
     * platform's. A client workspace without a key is told so; nothing else
     * is tried.
     *
     * @return array{ok: bool, message: string, latency_ms: int}
     */
    public function forWorkspace(TaurusWorkspace $workspace, string $target): array
    {
        if ($target === 'elevenlabs') {
            $credential = $workspace->credential(TaurusWorkspaceCredential::ELEVENLABS);
            if ($credential !== null && $credential->hasKey()) {
                return $this->voice((string) $credential->api_key, trim((string) $credential->voice_id) !== '');
            }

            if ($workspace->is_owner && trim((string) config('services.elevenlabs.api_key', '')) !== '') {
                return $this->handle('elevenlabs');
            }

            return $this->result(false, 'ElevenLabs needs an API key before it can be tested.', 0);
        }

        $resolved = $this->brain->clientFor($workspace, $target);
        if ($resolved === null) {
            return $this->result(false, $this->needs($target), 0);
        }

        return $this->llm(BrainStatus::label($target), fn (): AiClient => $resolved['client'], $resolved['secret']);
    }

    private function needs(string $provider): string
    {
        $what = $provider === 'custom' ? 'an API key and a base URL' : 'an API key';

        return BrainStatus::label($provider)." needs {$what} before it can be tested.";
    }

    /**
     * @param  \Closure(): AiClient  $client
     * @return array{ok: bool, message: string, latency_ms: int}
     */
    private function llm(string $label, \Closure $client, string $secret): array
    {
        $start = hrtime(true);

        try {
            $result = $client()->complete(new AiMessage(
                user: 'Reply with the single word OK.',
                maxTokens: 16,
            ));
        } catch (Throwable $e) {
            return $this->result(false, $this->failure($label, $e, $secret), $this->since($start));
        }

        $ms = $this->since($start);

        return $this->result(true, "{$label} answered in {$ms} ms using {$result->model}.", $ms);
    }

    /**
     * @return array{ok: bool, message: string, latency_ms: int}
     */
    private function voice(string $key, bool $hasVoiceId): array
    {
        $key = trim($key);

        if ($key === '') {
            return $this->result(false, 'ElevenLabs needs an API key before it can be tested.', 0);
        }

        $start = hrtime(true);

        try {
            $response = Http::withHeaders(['xi-api-key' => $key])
                ->acceptJson()
                ->timeout(15)
                ->get('https://api.elevenlabs.io/v1/voices');
        } catch (Throwable $e) {
            return $this->result(false, $this->failure('ElevenLabs', $e, $key), $this->since($start));
        }

        $ms = $this->since($start);

        if (! $response->successful()) {
            return $this->result(false, "ElevenLabs refused the key (HTTP {$response->status()}).", $ms);
        }

        $voices = count((array) $response->json('voices', []));
        $message = "ElevenLabs answered in {$ms} ms — {$voices} voices on this account.";

        if (! $hasVoiceId) {
            $message .= ' Add a voice ID so Taurus can speak.';
        }

        return $this->result(true, $message, $ms);
    }

    /** A plain failure line: who failed and the HTTP status, never the key. */
    private function failure(string $label, Throwable $e, string $secret): string
    {
        $message = match (true) {
            $e instanceof RequestException => "{$label} refused the request (HTTP {$e->response->status()}).",
            $e instanceof ConnectionException => "Could not reach {$label} — check the base URL and the server's network.",
            default => "{$label} test failed: ".mb_substr($e->getMessage(), 0, 160),
        };

        // Belt and braces: a vendor error that echoes the key never reaches the browser.
        $secret = trim($secret);

        return $secret !== '' ? str_replace($secret, '••••', $message) : $message;
    }

    private function since(int $start): int
    {
        return (int) ((hrtime(true) - $start) / 1_000_000);
    }

    /**
     * @return array{ok: bool, message: string, latency_ms: int}
     */
    private function result(bool $ok, string $message, int $ms): array
    {
        return ['ok' => $ok, 'message' => $message, 'latency_ms' => $ms];
    }
}
