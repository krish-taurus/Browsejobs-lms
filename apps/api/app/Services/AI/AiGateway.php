<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Enums\AiEventStatus;
use App\Enums\AiPurpose;
use App\Models\AiEvent;
use App\Models\User;
use App\Support\AI\ProviderResolver;
use App\Support\Tenancy\TenantContext;
use Throwable;

/**
 * The single AI gateway (CLAUDE.md AI Service Layer). Renders a versioned prompt,
 * enforces the per-student daily token budget, calls the swappable AiClient
 * transport, and logs every call to `ai_events` (purpose, model, tokens, cost,
 * latency). Consumers wrap this in a queued job per CLAUDE.md.
 */
final class AiGateway
{
    public function __construct(
        private readonly AiClient $client,
        private readonly PromptRepository $prompts,
    ) {}

    /**
     * @param  array<string, string>  $vars
     * @param  array{system?: string, model?: string, max_tokens?: int}  $opts
     */
    public function complete(User $user, AiPurpose $purpose, string $promptName, int $version, array $vars = [], array $opts = []): AiResult
    {
        return app(TenantContext::class)->run($user->tenant, function () use ($user, $purpose, $promptName, $version, $vars, $opts): AiResult {
            $this->assertWithinBudget($user, $purpose);

            $prompt = $this->prompts->render($promptName, $version, $vars);
            // Default to the ACTIVE provider's model, not the global Anthropic
            // default — otherwise e.g. Kimi is sent "claude-sonnet-5" and rejects it.
            $model = $opts['model'] ?? $this->defaultModel();

            $message = new AiMessage(
                user: $prompt,
                system: $opts['system'] ?? null,
                model: $model,
                maxTokens: $this->ceiling($opts['max_tokens'] ?? null),
            );

            $start = hrtime(true);

            try {
                $result = $this->client->complete($message);
            } catch (Throwable $e) {
                $this->log($user, $purpose, $model, 0, 0, 0, 0, AiEventStatus::Failed, ['error' => substr($e->getMessage(), 0, 250)]);
                throw $e;
            }

            $latencyMs = (int) ((hrtime(true) - $start) / 1_000_000);
            $cost = $this->cost($result->promptTokens, $result->completionTokens);

            $this->log($user, $purpose, $result->model, $result->promptTokens, $result->completionTokens, $cost, $latencyMs, AiEventStatus::Ok);

            return $result;
        });
    }

    /**
     * A feature's token ceiling with the configured headroom applied.
     *
     * Null stays null — that means "use the provider default", and scaling a
     * default nobody set here would be inventing a number.
     */
    private function ceiling(?int $requested): ?int
    {
        if ($requested === null) {
            return null;
        }

        return (int) ceil($requested * (float) config('ai.token_headroom', 1.0));
    }

    /** The model of the provider actually in use, so each vendor gets a name it knows. */
    private function defaultModel(): string
    {
        $provider = app(ProviderResolver::class)->active();
        if ($provider !== null) {
            return (string) config("ai.providers.{$provider}.model", config('ai.model'));
        }

        return (string) config('ai.model');
    }

    /** Tokens a student has spent so far today across all AI calls. */
    public function usedTokensToday(User $user): int
    {
        return (int) AiEvent::query()
            ->where('user_id', $user->id)
            ->where('created_at', '>=', now()->startOfDay())
            ->sum('total_tokens');
    }

    private function assertWithinBudget(User $user, AiPurpose $purpose): void
    {
        $budget = (int) config('ai.daily_token_budget');
        if ($budget <= 0) {
            return;
        }

        $used = $this->usedTokensToday($user);
        if ($used >= $budget) {
            $this->log($user, $purpose, (string) config('ai.model'), 0, 0, 0, 0, AiEventStatus::BudgetExceeded, ['used' => $used, 'budget' => $budget]);

            throw new AiBudgetExceeded("Daily AI token budget reached ({$used}/{$budget}).");
        }
    }

    private function cost(int $promptTokens, int $completionTokens): int
    {
        $inMicros = (int) config('ai.pricing.input_micros_per_mtok');
        $outMicros = (int) config('ai.pricing.output_micros_per_mtok');

        return intdiv($promptTokens * $inMicros, 1_000_000) + intdiv($completionTokens * $outMicros, 1_000_000);
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function log(User $user, AiPurpose $purpose, string $model, int $promptTokens, int $completionTokens, int $costMicros, int $latencyMs, AiEventStatus $status, array $meta = []): AiEvent
    {
        return AiEvent::query()->create([
            'tenant_id' => $user->tenant_id,
            'user_id' => $user->id,
            'purpose' => $purpose->value,
            'model' => $model,
            'prompt_tokens' => $promptTokens,
            'completion_tokens' => $completionTokens,
            'total_tokens' => $promptTokens + $completionTokens,
            'cost_micros' => $costMicros,
            'latency_ms' => $latencyMs,
            'status' => $status->value,
            'meta' => $meta ?: null,
        ]);
    }
}
