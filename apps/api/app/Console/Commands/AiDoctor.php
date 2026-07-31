<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\AiEvent;
use App\Services\AI\AiClient;
use App\Services\AI\AiMessage;
use App\Services\AI\AnthropicClient;
use App\Services\AI\JsonOutput;
use App\Services\AI\OpenAiCompatibleClient;
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
    protected $signature = 'ai:doctor {--prompt=Reply with the single word OK.} {--all : Test every configured provider, not just the active one} {--raw : Print the reply verbatim and report whether it parses as JSON} {--max-tokens=16 : Ceiling for the test call; raise it to see a full JSON reply}';

    protected $description = 'Diagnose the active AI provider with a live test call.';

    /** Same preference order the resolver uses, so the output reads in the order keys are picked. */
    private const PROVIDERS = ['anthropic', 'openai', 'kimi', 'deepseek', 'grok', 'custom'];

    public function handle(ProviderResolver $resolver): int
    {
        $this->line('Requested provider: '.(string) config('ai.provider', 'anthropic'));

        if ($this->option('all')) {
            return $this->testEveryProvider($resolver);
        }

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
            $maxTokens = max(1, (int) $this->option('max-tokens'));

            $result = app(AiClient::class)->complete(new AiMessage(
                user: (string) $this->option('prompt'),
                system: null,
                maxTokens: $maxTokens,
            ));

            $this->info("SUCCESS — model {$result->model}, {$result->promptTokens}+{$result->completionTokens} tokens.");

            if ($this->option('raw')) {
                $this->reportRaw($result->text, $result->completionTokens, $maxTokens, $result->stopReason);

                return self::SUCCESS;
            }

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

    /**
     * Try every provider that has a key.
     *
     * The active provider is only one of them, and a stale key on an earlier
     * provider silently shadows a good one — the resolver honours an explicit
     * choice whenever that provider has *a* key, working or not. Seeing every
     * result side by side is what turns "AI is broken" into "this key is dead,
     * that one works".
     */
    private function testEveryProvider(ProviderResolver $resolver): int
    {
        $active = $resolver->active();
        $rows = [];
        $anyWorked = false;

        foreach (self::PROVIDERS as $provider) {
            if (! $resolver->isConfigured($provider)) {
                continue;
            }

            [$outcome, $detail] = $this->probe($provider);
            $anyWorked = $anyWorked || $outcome === 'OK';

            $rows[] = [
                $provider.($provider === $active ? ' (active)' : ''),
                (string) (config("ai.providers.{$provider}.model") ?: '—'),
                $outcome,
                $detail,
            ];
        }

        if ($rows === []) {
            $this->error('No provider is configured — no API key is set for any provider.');

            return self::FAILURE;
        }

        $this->newLine();
        $this->table(['Provider', 'Model', 'Result', 'Detail'], $rows);

        if (! $anyWorked) {
            $this->error('Every configured provider failed. AI features cannot work until one of these succeeds.');

            return self::FAILURE;
        }

        if ($active !== null && ! $this->workedFor($active, $rows)) {
            $this->newLine();
            $this->warn("The active provider [{$active}] is failing while another one works.");
            $this->line('Set Active provider in Admin → Settings → AI / LLM to the one that succeeded.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * One live call against a named provider, reported rather than thrown.
     *
     * @return array{0: string, 1: string}
     */
    private function probe(string $provider): array
    {
        /** @var array{driver?: string, api_key?: string, base_url?: string, model?: string} $config */
        $config = (array) config("ai.providers.{$provider}");

        try {
            $client = match ($config['driver'] ?? null) {
                'anthropic' => new AnthropicClient($config),
                'openai_compatible' => new OpenAiCompatibleClient($config),
                default => null,
            };

            if ($client === null) {
                return ['SKIPPED', 'no driver configured'];
            }

            $result = $client->complete(new AiMessage(
                user: (string) $this->option('prompt'),
                system: null,
                maxTokens: 16,
            ));

            return ['OK', "{$result->promptTokens}+{$result->completionTokens} tokens"];
        } catch (RequestException $e) {
            // Generous truncation: a provider's rejection usually *names*
            // the fix ("supported model names are …"), and cutting it at a
            // tidy width threw away the one sentence worth reading.
            return ['FAILED', 'HTTP '.$e->response->status().' '.mb_substr(trim($e->response->body()), 0, 300)];
        } catch (Throwable $e) {
            return ['FAILED', class_basename($e).': '.mb_substr($e->getMessage(), 0, 300)];
        }
    }

    /**
     * @param  list<array{0: string, 1: string, 2: string, 3: string}>  $rows
     */
    private function workedFor(string $provider, array $rows): bool
    {
        foreach ($rows as $row) {
            if (str_starts_with($row[0], $provider) && $row[2] === 'OK') {
                return true;
            }
        }

        return false;
    }

    /**
     * Show the reply exactly as it arrived, and whether our parser can read it.
     *
     * Structured features fail when a model wraps its JSON, pads it with
     * reasoning, or runs out of tokens mid-object. All three look identical
     * from the outside — "the AI did nothing" — and none of them are visible
     * anywhere, because ai_events stores tokens and cost but not the reply.
     */
    private function reportRaw(string $text, int $completionTokens, int $maxTokens, string $stopReason): void
    {
        $this->newLine();
        $this->line('--- reply begins ---');
        $this->line($text);
        $this->line('--- reply ends ---');
        $this->newLine();

        $parsed = JsonOutput::object($text);
        $this->line('Parses as a JSON object: '.($parsed !== null ? 'yes ('.count($parsed).' keys)' : 'NO'));
        $this->line('Stop reason: '.($stopReason ?: 'unknown'));

        if ($completionTokens >= $maxTokens) {
            $this->newLine();
            $this->warn('The reply hit the token ceiling and is probably cut off.');
            $this->line("Re-run with a higher --max-tokens than {$maxTokens} to see the whole thing.");
        }

        if ($parsed === null && trim($text) !== '') {
            $this->newLine();
            $this->line('The model answered but not in a shape we can read. If the text above is');
            $this->line('cut off mid-object, the feature needs a higher token ceiling for this');
            $this->line('model; if it is prose or reasoning, the model is ignoring "JSON only".');
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
