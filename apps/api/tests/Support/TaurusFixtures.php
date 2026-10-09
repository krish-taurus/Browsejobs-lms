<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Actions\Taurus\RotateIngestToken;
use App\Models\TaurusAgent;
use App\Models\TaurusTask;
use App\Models\TaurusWorkspace;
use App\Models\TaurusWorkspaceCredential;
use App\Models\TaurusWorkspaceMember;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Taurus\WorkspaceBrainStatus;
use Illuminate\Support\Str;

/**
 * Builders for the Taurus test suites (ADR 0052). Everything is created with
 * an explicit tenant_id, so tests never depend on an active TenantContext.
 */
final class TaurusFixtures
{
    /** Provider ids whose platform keys every Taurus test starts without. */
    public const PROVIDERS = ['anthropic', 'openai', 'gemini', 'kimi', 'deepseek', 'grok', 'groq', 'custom'];

    /** Clear every platform AI/voice key, whatever the local .env holds. */
    public static function cleanPlatform(): void
    {
        foreach (self::PROVIDERS as $p) {
            config(["ai.providers.{$p}.api_key" => '']);
        }

        config([
            'ai.provider' => 'auto',
            'ai.providers.custom.base_url' => '',
            'taurus.brain.provider' => 'platform',
            'taurus.brain.model' => null,
            'taurus.owner_emails' => '',
            'services.elevenlabs.api_key' => '',
            'services.elevenlabs.voice_id' => '',
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function workspace(Tenant $tenant, array $attributes = []): TaurusWorkspace
    {
        $name = $attributes['name'] ?? 'Acme '.Str::random(6);

        return TaurusWorkspace::query()->create([
            'tenant_id' => $tenant->id,
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(4)),
            'status' => 'active',
            'is_owner' => false,
            ...$attributes,
        ]);
    }

    public static function member(TaurusWorkspace $workspace, User $user, string $role = 'owner'): TaurusWorkspaceMember
    {
        return TaurusWorkspaceMember::query()->create([
            'tenant_id' => $workspace->tenant_id,
            'taurus_workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'role' => $role,
        ]);
    }

    /** Give the workspace an ingest token and return the raw bearer value. */
    public static function token(TaurusWorkspace $workspace): string
    {
        $token = 'tau_'.Str::random(40);
        $workspace->forceFill([
            'ingest_token_hash' => RotateIngestToken::hash($token),
            'ingest_token_last4' => substr($token, -4),
        ])->save();

        return $token;
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    public static function credential(TaurusWorkspace $workspace, string $provider, string $key, array $extra = []): TaurusWorkspaceCredential
    {
        return TaurusWorkspaceCredential::query()->create([
            'tenant_id' => $workspace->tenant_id,
            'taurus_workspace_id' => $workspace->id,
            'provider' => $provider,
            'api_key' => $key,
            'key_last4' => WorkspaceBrainStatus::last4($key),
            ...$extra,
        ]);
    }

    /** A bot on the workspace's floor, seen `minutesAgo` minutes ago (null = never). */
    public static function agent(TaurusWorkspace $workspace, string $slug, string $status = 'working', ?int $minutesAgo = 1, string $floor = 'ops'): TaurusAgent
    {
        return TaurusAgent::query()->create([
            'tenant_id' => $workspace->tenant_id,
            'taurus_workspace_id' => $workspace->id,
            'floor' => $floor,
            'slug' => $slug,
            'name' => ucfirst($slug),
            'status' => $status,
            'last_seen_at' => $minutesAgo === null ? null : now()->subMinutes($minutesAgo),
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function task(TaurusAgent $agent, string $ref = 'pay-1', array $attributes = []): TaurusTask
    {
        return TaurusTask::query()->create([
            'tenant_id' => $agent->tenant_id,
            'taurus_workspace_id' => $agent->taurus_workspace_id,
            'agent_id' => $agent->id,
            'ref' => $ref,
            'title' => 'Pay vendor invoice',
            'status' => 'needs_approval',
            'approval_action' => 'Pay ₹48,000 to Acme',
            'risk' => 'high',
            ...$attributes,
        ]);
    }
}
