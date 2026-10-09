<?php

declare(strict_types=1);

namespace App\Actions\Taurus;

use App\Models\TaurusWorkspace;
use App\Models\TaurusWorkspaceCredential;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use App\Support\Taurus\WorkspaceBrainStatus;
use Illuminate\Support\Facades\DB;

/**
 * Saves a workspace's "Brain & voice" form (ADR 0052): its brain choice and
 * its own encrypted credentials. A blank key keeps the stored one (the form
 * never shows it); DELETE …/brain/providers/{id} is the way to forget a key.
 * The audit records which fields changed — never a value.
 */
final class SaveWorkspaceBrain
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array<string, mixed>  $input  validated UpdateWorkspaceBrainRequest payload
     */
    public function handle(TaurusWorkspace $workspace, array $input, User $actor): void
    {
        $changed = DB::transaction(function () use ($workspace, $input): array {
            $changed = [];

            foreach (['brain_provider', 'brain_model'] as $field) {
                if (array_key_exists($field, $input)) {
                    $value = trim((string) ($input[$field] ?? ''));
                    $workspace->{$field} = $value !== '' ? $value : null;
                    $changed[] = $field;
                }
            }
            $workspace->save();

            foreach ((array) ($input['providers'] ?? []) as $provider => $fields) {
                $changed = [...$changed, ...$this->saveCredential($workspace, (string) $provider, (array) $fields, ['model', 'base_url'])];
            }

            if (is_array($input['voice'] ?? null)) {
                $changed = [...$changed, ...$this->saveCredential($workspace, TaurusWorkspaceCredential::ELEVENLABS, $input['voice'], ['voice_id', 'model'])];
            }

            return $changed;
        });

        if ($changed !== []) {
            $this->audit->log('taurus.brain.updated', $workspace, ['fields' => $changed], $actor);
        }
    }

    /**
     * @param  array<string, mixed>  $fields
     * @param  list<string>  $plain  non-secret columns this credential accepts
     * @return list<string> changed field names, e.g. "openai.api_key"
     */
    private function saveCredential(TaurusWorkspace $workspace, string $provider, array $fields, array $plain): array
    {
        $changed = [];

        /** @var TaurusWorkspaceCredential $credential */
        $credential = $workspace->credentials()->firstOrNew(['provider' => $provider]);
        if (! $credential->exists) {
            $credential->api_key = '';
        }

        $key = trim((string) ($fields['api_key'] ?? ''));
        if ($key !== '') {
            $credential->api_key = $key;
            $credential->key_last4 = WorkspaceBrainStatus::last4($key);
            $changed[] = "{$provider}.api_key";
        }

        foreach ($plain as $column) {
            if (array_key_exists($column, $fields)) {
                $value = trim((string) ($fields[$column] ?? ''));
                $credential->{$column} = $value !== '' ? $value : null;
                $changed[] = "{$provider}.{$column}";
            }
        }

        if ($changed !== []) {
            $credential->save();
        }

        return $changed;
    }
}
