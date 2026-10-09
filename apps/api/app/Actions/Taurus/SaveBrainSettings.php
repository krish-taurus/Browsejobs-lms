<?php

declare(strict_types=1);

namespace App\Actions\Taurus;

use App\Models\User;
use App\Support\Settings\PlatformSettings;

/**
 * Saves the console's "Brain & voice" form through the platform settings
 * whitelist (ADR 0052) — so every key is encrypted at rest, a blank secret
 * keeps the existing one, and the audit records which keys changed but never
 * their values. LLM keys are the shared `ai` group keys: a key entered here is
 * the same key the rest of the platform uses for that provider.
 */
final class SaveBrainSettings
{
    public function __construct(private readonly PlatformSettings $settings) {}

    /**
     * @param  array<string, mixed>  $input  validated UpdateBrainRequest payload
     */
    public function handle(array $input, User $actor): void
    {
        $out = [];

        if (array_key_exists('brain_provider', $input)) {
            $out['taurus']['brain_provider'] = (string) ($input['brain_provider'] ?? '');
        }

        if (array_key_exists('brain_model', $input)) {
            $out['taurus']['brain_model'] = (string) ($input['brain_model'] ?? '');
        }

        foreach ((array) ($input['providers'] ?? []) as $provider => $fields) {
            foreach (['api_key', 'model', 'base_url'] as $field) {
                if (is_array($fields) && array_key_exists($field, $fields)) {
                    $out['ai']["{$provider}_{$field}"] = (string) ($fields[$field] ?? '');
                }
            }
        }

        $voice = (array) ($input['voice'] ?? []);
        foreach (['api_key', 'voice_id', 'model'] as $field) {
            if (array_key_exists($field, $voice)) {
                $out['taurus']["elevenlabs_{$field}"] = (string) ($voice[$field] ?? '');
            }
        }

        if ($out !== []) {
            $this->settings->save($out, $actor);
        }
    }
}
