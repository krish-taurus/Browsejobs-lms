<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Taurus AI command centre (ADR 0052).
 *
 * Taurus is multi-workspace: every business (the founder's own "Taurus HQ"
 * included) gets a workspace with its own members, its own bot ingest token
 * and its own LLM / ElevenLabs credentials. Bots report into four
 * workspace-scoped tables: agents, the tasks they run (some waiting for a
 * human decision), a plain-English event feed, and spend exactly as the bot
 * reported it — the console never estimates a cost.
 *
 * Every index and foreign key is named explicitly: generated names on these
 * tables would be close to MySQL's 64-character identifier limit.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('taurus_workspaces', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->foreign('tenant_id', 'tau_ws_tenant_fk')->references('id')->on('tenants')->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('slug', 64);
            $table->string('status', 16)->default('active'); // active | suspended
            $table->boolean('is_owner')->default(false); // the founder's "Taurus HQ" — one per tenant
            $table->string('brain_provider', 32)->nullable(); // 'platform' (owner only) or a provider id
            $table->string('brain_model', 120)->nullable();
            $table->char('ingest_token_hash', 64)->nullable(); // sha256 of the bearer token
            $table->string('ingest_token_last4', 8)->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->foreign('created_by', 'tau_ws_creator_fk')->references('id')->on('users')->nullOnDelete();
            $table->timestamps();

            $table->index('tenant_id', 'tau_ws_tenant_idx');
            $table->unique(['tenant_id', 'slug'], 'tau_ws_slug_uq');
            $table->unique('ingest_token_hash', 'tau_ws_token_uq');
            $table->index(['tenant_id', 'is_owner'], 'tau_ws_owner_idx');
        });

        Schema::create('taurus_workspace_members', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->foreign('tenant_id', 'tau_wsm_tenant_fk')->references('id')->on('tenants')->cascadeOnDelete();
            $table->unsignedBigInteger('taurus_workspace_id');
            $table->foreign('taurus_workspace_id', 'tau_wsm_ws_fk')->references('id')->on('taurus_workspaces')->cascadeOnDelete();
            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id', 'tau_wsm_user_fk')->references('id')->on('users')->cascadeOnDelete();
            $table->string('role', 16); // owner | admin | viewer
            $table->timestamps();

            $table->index('tenant_id', 'tau_wsm_tenant_idx');
            $table->unique(['taurus_workspace_id', 'user_id'], 'tau_wsm_ws_user_uq');
            $table->index('user_id', 'tau_wsm_user_idx');
        });

        Schema::create('taurus_workspace_credentials', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->foreign('tenant_id', 'tau_wsc_tenant_fk')->references('id')->on('tenants')->cascadeOnDelete();
            $table->unsignedBigInteger('taurus_workspace_id');
            $table->foreign('taurus_workspace_id', 'tau_wsc_ws_fk')->references('id')->on('taurus_workspaces')->cascadeOnDelete();
            $table->string('provider', 32); // anthropic | openai | gemini | kimi | deepseek | grok | groq | custom | elevenlabs
            $table->text('api_key'); // encrypted cast — never serialized
            $table->string('key_last4', 8)->nullable();
            $table->string('model', 120)->nullable();
            $table->string('base_url', 255)->nullable();
            $table->string('voice_id', 120)->nullable(); // elevenlabs only
            $table->timestamps();

            $table->index('tenant_id', 'tau_wsc_tenant_idx');
            $table->unique(['taurus_workspace_id', 'provider'], 'tau_wsc_ws_provider_uq');
        });

        Schema::create('taurus_workspace_invites', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->foreign('tenant_id', 'tau_wsi_tenant_fk')->references('id')->on('tenants')->cascadeOnDelete();
            $table->unsignedBigInteger('taurus_workspace_id');
            $table->foreign('taurus_workspace_id', 'tau_wsi_ws_fk')->references('id')->on('taurus_workspaces')->cascadeOnDelete();
            $table->string('email');
            $table->string('name', 120)->nullable();
            $table->string('role', 16);
            $table->char('token_hash', 64);
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->unsignedBigInteger('invited_by')->nullable();
            $table->foreign('invited_by', 'tau_wsi_inviter_fk')->references('id')->on('users')->nullOnDelete();
            $table->timestamps();

            $table->index('tenant_id', 'tau_wsi_tenant_idx');
            $table->unique('token_hash', 'tau_wsi_token_uq');
            $table->index(['taurus_workspace_id', 'email'], 'tau_wsi_ws_email_idx');
        });

        Schema::create('taurus_agents', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->foreign('tenant_id', 'tau_agents_tenant_fk')->references('id')->on('tenants')->cascadeOnDelete();
            $table->unsignedBigInteger('taurus_workspace_id');
            $table->foreign('taurus_workspace_id', 'tau_agents_ws_fk')->references('id')->on('taurus_workspaces')->cascadeOnDelete();
            $table->string('floor', 20); // ops | recruitment
            $table->string('slug', 64);
            $table->string('name', 120);
            $table->string('zone', 80)->nullable();
            $table->string('role', 120)->nullable();
            $table->string('platform', 40)->nullable();
            $table->string('status', 16)->default('idle'); // App\Enums\TaurusAgentStatus
            $table->string('current_task', 240)->nullable();
            $table->decimal('progress', 5, 4)->default(0);
            $table->json('meta')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->index('tenant_id', 'tau_agents_tenant_idx');
            $table->index('taurus_workspace_id', 'tau_agents_ws_idx');
            $table->unique(['taurus_workspace_id', 'floor', 'slug'], 'tau_agents_floor_slug_uq');
        });

        Schema::create('taurus_tasks', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->foreign('tenant_id', 'tau_tasks_tenant_fk')->references('id')->on('tenants')->cascadeOnDelete();
            $table->unsignedBigInteger('taurus_workspace_id');
            $table->foreign('taurus_workspace_id', 'tau_tasks_ws_fk')->references('id')->on('taurus_workspaces')->cascadeOnDelete();
            $table->unsignedBigInteger('agent_id');
            $table->foreign('agent_id', 'tau_tasks_agent_fk')->references('id')->on('taurus_agents')->cascadeOnDelete();
            $table->string('ref', 120);
            $table->string('title', 240);
            $table->string('status', 20)->default('queued'); // App\Enums\TaurusTaskStatus
            $table->decimal('progress', 5, 4)->default(0);
            $table->string('risk', 8)->nullable(); // low | high
            $table->string('approval_action', 240)->nullable();
            $table->string('decision', 12)->nullable(); // approved | rejected
            $table->string('decision_note', 500)->nullable();
            $table->unsignedBigInteger('decided_by')->nullable();
            $table->foreign('decided_by', 'tau_tasks_decider_fk')->references('id')->on('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index('tenant_id', 'tau_tasks_tenant_idx');
            $table->unique(['agent_id', 'ref'], 'tau_tasks_agent_ref_uq');
            $table->index(['taurus_workspace_id', 'status'], 'tau_tasks_ws_status_idx');
            $table->index(['taurus_workspace_id', 'decided_at'], 'tau_tasks_ws_decided_idx');
        });

        Schema::create('taurus_events', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->foreign('tenant_id', 'tau_events_tenant_fk')->references('id')->on('tenants')->cascadeOnDelete();
            $table->unsignedBigInteger('taurus_workspace_id');
            $table->foreign('taurus_workspace_id', 'tau_events_ws_fk')->references('id')->on('taurus_workspaces')->cascadeOnDelete();
            $table->unsignedBigInteger('agent_id');
            $table->foreign('agent_id', 'tau_events_agent_fk')->references('id')->on('taurus_agents')->cascadeOnDelete();
            $table->unsignedBigInteger('task_id')->nullable();
            $table->foreign('task_id', 'tau_events_task_fk')->references('id')->on('taurus_tasks')->nullOnDelete();
            $table->string('kind', 16); // status | task | message | spend | approval
            $table->string('status', 20)->nullable();
            $table->string('message', 500);
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index('tenant_id', 'tau_events_tenant_idx');
            $table->index(['taurus_workspace_id', 'occurred_at'], 'tau_events_ws_occurred_idx');
        });

        Schema::create('taurus_spend_events', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->foreign('tenant_id', 'tau_spend_tenant_fk')->references('id')->on('tenants')->cascadeOnDelete();
            $table->unsignedBigInteger('taurus_workspace_id');
            $table->foreign('taurus_workspace_id', 'tau_spend_ws_fk')->references('id')->on('taurus_workspaces')->cascadeOnDelete();
            $table->unsignedBigInteger('agent_id')->nullable();
            $table->foreign('agent_id', 'tau_spend_agent_fk')->references('id')->on('taurus_agents')->nullOnDelete();
            $table->string('source', 80);
            $table->string('currency', 8)->nullable();
            // Amount × 1,000,000 exactly as the bot reported it. Never estimated.
            $table->bigInteger('amount_micros')->nullable();
            $table->decimal('units', 14, 4)->nullable();
            $table->string('unit_label', 40)->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index('tenant_id', 'tau_spend_tenant_idx');
            $table->index(['taurus_workspace_id', 'occurred_at'], 'tau_spend_ws_occurred_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('taurus_spend_events');
        Schema::dropIfExists('taurus_events');
        Schema::dropIfExists('taurus_tasks');
        Schema::dropIfExists('taurus_agents');
        Schema::dropIfExists('taurus_workspace_invites');
        Schema::dropIfExists('taurus_workspace_credentials');
        Schema::dropIfExists('taurus_workspace_members');
        Schema::dropIfExists('taurus_workspaces');
    }
};
