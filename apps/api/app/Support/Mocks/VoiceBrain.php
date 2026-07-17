<?php

declare(strict_types=1);

namespace App\Support\Mocks;

/**
 * Maps the platform's active LLM provider (config/ai.php, ADR 0016) onto a
 * Vapi assistant model block, so the voice interviewer is powered by the
 * same brain the tenant chose for text AI. Anthropic and OpenAI are native
 * Vapi providers; every other OpenAI-compatible vendor (Kimi/Moonshot,
 * DeepSeek, Grok, custom) rides Vapi's custom-llm passthrough.
 *
 * Note for custom-llm vendors: Vapi authenticates to the endpoint with the
 * custom-LLM credential registered in the Vapi dashboard (one-time setup) —
 * API keys are never sent in the per-call payload.
 */
final class VoiceBrain
{
    /**
     * @return array<string, mixed>
     */
    public static function modelConfig(string $systemPrompt): array
    {
        $provider = (string) config('ai.provider', 'anthropic');
        $settings = (array) config("ai.providers.{$provider}", []);
        $model = (string) ($settings['model'] ?? '');
        $messages = [['role' => 'system', 'content' => $systemPrompt]];

        if ($provider === 'openai' && $model !== '') {
            return ['provider' => 'openai', 'model' => $model, 'messages' => $messages];
        }

        if ($provider !== 'anthropic' && $provider !== 'openai') {
            $baseUrl = (string) ($settings['base_url'] ?? '');
            if ($model !== '' && $baseUrl !== '') {
                return [
                    'provider' => 'custom-llm',
                    'url' => rtrim($baseUrl, '/'),
                    'model' => $model,
                    'messages' => $messages,
                ];
            }
        }

        // Anthropic — and the safe fallback when the chosen provider is
        // misconfigured (empty model/base_url): a broken voice brain must
        // degrade to the default, not to a dead call.
        return [
            'provider' => 'anthropic',
            'model' => (string) config('ai.providers.anthropic.model', 'claude-sonnet-5'),
            'messages' => $messages,
        ];
    }
}
