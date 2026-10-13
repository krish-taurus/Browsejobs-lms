<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agentic hiring: something Taurus proposes to do in a client's pipeline
 * ("send L1 invites to the 8 shortlisted for Java Developer"), waiting for
 * the client's YES on WhatsApp, a spoken yes, or the Confirm button.
 *
 * A proposal is the whole decision: `params` names the exact rows it will
 * touch, so what runs on YES is what the client was shown — never a fresh
 * reading of the pipeline that could include people they did not see.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employer_agent_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employer_workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employer_job_id')->nullable()->constrained()->cascadeOnDelete();
            // Who asked for it; null when Taurus proposed it at a milestone.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            // The WhatsApp chat it was put to, so a YES there confirms this one.
            $table->foreignId('employer_whatsapp_link_id')->nullable()->constrained()->nullOnDelete();
            $table->string('channel', 20); // whatsapp | web | milestone
            $table->string('kind', 30); // start_round | move_stage | reject | job_status
            // One open milestone proposal per job and step ("l2 after l1").
            $table->string('topic', 60)->nullable();
            $table->json('params');
            $table->text('summary');
            $table->string('status', 20)->default('proposed'); // proposed | running | done | failed | cancelled | expired
            $table->text('result')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamp('expires_at');
            // Last time the client was asked on WhatsApp; a growing milestone
            // proposal ("3 cleared L1 … now 5") re-asks at most every so often.
            $table->timestamp('notified_at')->nullable();
            $table->timestamps();

            $table->index(['employer_workspace_id', 'status']);
            $table->index(['employer_job_id', 'topic', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employer_agent_actions');
    }
};
