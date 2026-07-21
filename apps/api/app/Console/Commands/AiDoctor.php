<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\AiEvent;
use App\Services\AI\AiClient;
use App\Services\AI\AiMessage;
use App\Support\AI\ProviderResolver;
use Illuminate\Console\Command;
use Illuminate\Http\Client\RequestException;
use Throwable;

/**
 * Diagnose the AI layer: print the resolved provider config and make one live
 * test call, surfacing the real error (bad key, wrong endpoint/model, blocked
 * network) that the queued jobs swallow. Also echoes the last stored failure.
 *
 * Usage: php artisan ai:doctor
 */
final class AiDoctor extends Command
{
    protected $signature = 'ai:doctor {--prompt=Reply with the single word OK.}';

    protected $description = 'Diagnose the active AI provider with a live test call.';

    public function handle(ProviderResolver $resolver): int
    {
        $this->line('Requested provider: '.(string) config('ai.provider', 'anthropic'));

        $provider = $resolver->active();
        if ($provider === null) {
            $this->error('No provider is configured — no API key is set for any provider.');

            return self::FAILURE;
        }

        $cfg = (array) config("ai.providers.{$provider}");
        $key = trim((string) ($cfg['api_key'] ?? ''));
        $this->line("Active provider:    {$provider}");
        $this->line('Base URL:           '.((string) ($cfg['base_url'] ?? '') ?: '(none)'));
        $this->line('Model:              '.((string) ($cfg['model'] ?? '') ?: '(none)'));
        $this->line('API key:            '.($key !== '' ? 'set ('.mb_strlen($key).' chars, ends '.mb_substr($key, -4).')' : 'MISSING'));
        $this->newLine();

        $this->line('Making a live test call…');
        try {
            $result = app(AiClient::class)->complete(new AiMessage(
                user: (string) $this->option('prompt'),
                system: null,
                maxTokens: 16,
            ));

            $this->info("SUCCESS — model {$result->model}, {$result->promptTokens}+{$result->completionTokens} tokens.");
            $this->line('Reply: '.trim($result->text));

            return self::SUCCESS;
        } catch (RequestException $e) {
            // The provider answered with an error — show its status + body verbatim.
            $this->error('FAILED — the provider rejected the request.');
            $this->error('HTTP '.$e->response->status().': '.mb_substr($e->response->body(), 0, 500));
            $this->hintFor($e->response->status(), $provider);

            return self::FAILURE;
        } catch (Throwable $e) {
            // Network/DNS/TLS — the request never got a response.
            $this->error('FAILED — could not reach the provider.');
            $this->error(class_basename($e).': '.$e->getMessage());
            $this->line('This is usually DNS, a firewall, or the wrong base URL. Confirm the server can reach the endpoint above.');

            return self::FAILURE;
        } finally {
            $last = AiEvent::query()->where('status', 'failed')->latest('id')->first();
            if ($last !== null && is_array($last->meta) && ($last->meta['error'] ?? null)) {
                $this->newLine();
                $this->line('Most recent stored failure: '.(string) $last->meta['error']);
            }
        }
    }

    private function hintFor(int $status, string $provider): void
    {
        $hint = match ($status) {
            401, 403 => 'The API key is invalid for this endpoint. For Kimi, a key from platform.moonshot.ai works against api.moonshot.ai; a key from platform.moonshot.cn needs base URL https://api.moonshot.cn/v1.',
            404 => "The model or path was not found — check the model name for {$provider} in Settings.",
            429 => 'Rate limited or out of credit on the provider account.',
            default => 'Check the key, base URL, and model in Settings → AI / LLM.',
        };
        $this->newLine();
        $this->line('Hint: '.$hint);
    }
}
