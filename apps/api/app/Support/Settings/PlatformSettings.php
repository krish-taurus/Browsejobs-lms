<?php

declare(strict_types=1);

namespace App\Support\Settings;

use App\Models\PlatformSetting;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Reads and writes the platform integration settings (LLM / WhatsApp / Vapi / Zoom) and
 * applies them over `config()` at boot, so the existing service clients pick up
 * admin-entered keys with `.env` as the fallback.
 *
 * The whitelist in config/platform_settings.php governs everything: only a listed
 * `group.key` can be stored, only its declared `config` path is overridden, and only a
 * non-`secret` field's value is ever returned to the browser. Registered as a singleton
 * so the per-request DB read happens once.
 */
final class PlatformSettings
{
    public function __construct(private readonly AuditLogger $audit) {}

    /** @var array<string, array<string, string>>|null group => key => value */
    private ?array $cache = null;

    /**
     * Config paths this instance has overridden, with the value they held
     * before (the .env default). Lets a cleared setting fall back to env in the
     * same process instead of keeping the stale override until the next boot.
     *
     * @var array<string, mixed>
     */
    private array $overridden = [];

    /**
     * Override `config()` from stored values. Called in AppServiceProvider::boot, before
     * any client binding resolves. A blank stored value is skipped so it falls back to
     * env. Fail-safe: if the table is missing (fresh install, mid-migration) or the DB is
     * unreachable, the app simply runs on its .env config.
     */
    public function apply(): void
    {
        try {
            if (! Schema::hasTable('platform_settings')) {
                return;
            }
        } catch (Throwable) {
            return;
        }

        $values = $this->values();

        foreach ($this->fields() as $field) {
            $path = $field['config'];
            $value = $values[$field['group']][$field['key']] ?? '';

            if ($value !== '') {
                if (! array_key_exists($path, $this->overridden)) {
                    $this->overridden[$path] = config($path);
                }
                config([$path => $value]);
            } elseif (array_key_exists($path, $this->overridden)) {
                // Was ours, now blank/removed: hand the path back to env.
                config([$path => $this->overridden[$path]]);
                unset($this->overridden[$path]);
            }
        }
    }

    /**
     * Drop the per-process cache and re-apply. A queue worker is long-lived and
     * reads settings once at boot, so a key saved in the admin panel afterwards
     * wouldn't reach it — calling this before each job keeps workers current
     * without a manual restart.
     */
    public function refresh(): void
    {
        $this->cache = null;
        $this->apply();
    }

    /**
     * The admin form schema with current values. Secret values are never included — only
     * whether one is set, plus a masked hint. Non-secret values are returned in full.
     *
     * Owner-only groups (`owner_only => true`, e.g. Taurus) are left out unless
     * asked for: the generic Settings page must not reveal or edit them.
     *
     * @return array<int, array<string, mixed>>
     */
    public function schema(bool $includeOwnerOnly = false): array
    {
        $values = $this->values();
        $groups = [];

        foreach ((array) config('platform_settings.groups') as $groupKey => $group) {
            if (! $includeOwnerOnly && ($group['owner_only'] ?? false)) {
                continue;
            }

            $fields = [];
            foreach ($group['fields'] as $field) {
                $stored = $values[$groupKey][$field['key']] ?? '';
                $isSecret = ($field['type'] ?? 'text') === 'secret';

                $fields[] = [
                    'key' => $field['key'],
                    'label' => $field['label'],
                    'type' => $field['type'] ?? 'text',
                    'options' => $field['options'] ?? null,
                    'set' => $stored !== '',
                    // A secret never leaves the server; a plain field round-trips its value.
                    'value' => $isSecret ? null : $stored,
                    'preview' => $isSecret && $stored !== '' ? $this->mask($stored) : null,
                ];
            }

            $groups[] = [
                'key' => $groupKey,
                'label' => $group['label'],
                'help' => $group['help'] ?? null,
                'fields' => $fields,
            ];
        }

        return $groups;
    }

    /**
     * Persist submitted values. Input is `group => [key => value]`. Only whitelisted
     * keys are written; a blank secret is ignored so an existing secret is not wiped by
     * an empty form field. The audit records which keys changed, never their values.
     *
     * @param  array<string, array<string, mixed>>  $input
     */
    public function save(array $input, ?User $actor = null): void
    {
        $changed = [];

        foreach ($this->fields() as $field) {
            $group = $field['group'];
            $key = $field['key'];

            if (! array_key_exists($group, $input) || ! array_key_exists($key, $input[$group])) {
                continue;
            }

            $value = trim((string) $input[$group][$key]);
            $isSecret = ($field['type'] ?? 'text') === 'secret';

            // Blank secret = "leave unchanged" (the form never shows the current secret,
            // so an empty box must not clear it). A blank plain field does clear it.
            if ($isSecret && $value === '') {
                continue;
            }

            // A select may only hold one of its declared options. The form is a
            // <select> so this cannot happen by hand, but the endpoint takes
            // arbitrary JSON, and an unrecognised value here would land in a
            // config path that something else resolves later — a bad provider
            // name would surface as a 500 on a candidate's submission, far from
            // the setting that caused it.
            if (($field['type'] ?? 'text') === 'select'
                && $field['options'] !== []
                && ! in_array($value, $field['options'], true)) {
                continue;
            }

            PlatformSetting::query()->updateOrCreate(
                ['group' => $group, 'key' => $key],
                ['value' => $value, 'updated_by' => $actor?->id],
            );

            $changed[] = "{$group}.{$key}";
        }

        if ($changed !== []) {
            $this->cache = null;
            $this->apply();
            $this->audit->log('platform_settings.updated', null, ['keys' => $changed], $actor);
        }
    }

    /**
     * save() for the generic Admin → Settings page: owner-only groups are
     * dropped from the input, so that page can never write them even though
     * it shares the gate-free service underneath.
     *
     * @param  array<string, array<string, mixed>>  $input
     */
    public function saveShared(array $input, ?User $actor = null): void
    {
        foreach ($this->ownerOnlyGroups() as $group) {
            unset($input[$group]);
        }

        $this->save($input, $actor);
    }

    /** @return list<string> */
    public function ownerOnlyGroups(): array
    {
        $out = [];
        foreach ((array) config('platform_settings.groups') as $groupKey => $group) {
            if ($group['owner_only'] ?? false) {
                $out[] = (string) $groupKey;
            }
        }

        return $out;
    }

    /**
     * Remove stored values outright — the explicit "forget this key" that
     * save() deliberately refuses for a blank secret. The config path falls
     * back to its .env default immediately. Only whitelisted keys are touched;
     * the audit records which keys were cleared, never their values.
     *
     * @param  array<string, list<string>>  $keys  group => [key, …]
     */
    public function clear(array $keys, ?User $actor = null): void
    {
        $cleared = [];

        foreach ($this->fields() as $field) {
            if (! in_array($field['key'], $keys[$field['group']] ?? [], true)) {
                continue;
            }

            $deleted = PlatformSetting::query()
                ->where('group', $field['group'])
                ->where('key', $field['key'])
                ->delete();

            if ($deleted > 0) {
                $cleared[] = "{$field['group']}.{$field['key']}";
            }
        }

        if ($cleared !== []) {
            $this->cache = null;
            $this->apply();
            $this->audit->log('platform_settings.cleared', null, ['keys' => $cleared], $actor);
        }
    }

    /**
     * All stored values, decrypted, grouped. Cached for the request.
     *
     * @return array<string, array<string, string>>
     */
    private function values(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        $out = [];
        foreach (PlatformSetting::query()->get(['group', 'key', 'value']) as $row) {
            $out[$row->group][$row->key] = (string) $row->value;
        }

        return $this->cache = $out;
    }

    /**
     * Flattened whitelist: each field with its group and config path.
     *
     * @return list<array{group: string, key: string, type: string, config: string, options: list<string>}>
     */
    private function fields(): array
    {
        $fields = [];
        foreach ((array) config('platform_settings.groups') as $groupKey => $group) {
            foreach ($group['fields'] as $field) {
                $fields[] = [
                    'group' => $groupKey,
                    'key' => $field['key'],
                    'type' => $field['type'] ?? 'text',
                    'config' => $field['config'],
                    'options' => array_values((array) ($field['options'] ?? [])),
                ];
            }
        }

        return $fields;
    }

    /** Last four characters, the rest dotted — enough to recognise a key, useless to steal. */
    private function mask(string $value): string
    {
        $tail = mb_substr($value, -4);

        return '••••'.$tail;
    }
}
