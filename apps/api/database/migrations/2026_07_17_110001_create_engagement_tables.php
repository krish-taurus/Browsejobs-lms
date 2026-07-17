<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * P3.7 (PRD §6.18 + §6.19): offer celebrations with consent-gated
     * broadcasts + per-recipient AI gap guidance; curated Market Pulse items
     * with a per-day AI digest; and the Content Hub feed.
     */
    public function up(): void
    {
        Schema::create('celebrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            // The celebrated student — kept even in anonymous mode so the
            // guidance card can compare against their mastery.
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->string('display_mode'); // named | anonymous
            $table->string('anonymous_label')->nullable(); // "A Data Engineering student from batch DE-202605"
            $table->string('role_title');
            $table->string('company')->nullable();
            // PRD: broadcast ONLY with explicit consent — recorded, not implied.
            $table->timestamp('consented_at');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'published_at']);
        });

        Schema::create('celebration_guidances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('celebration_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('intro');
            $table->json('actions'); // exactly three concrete actions
            $table->string('source'); // ai | fallback
            $table->timestamps();

            $table->unique(['celebration_id', 'user_id']);
            $table->index('tenant_id');
        });

        Schema::create('pulse_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('url');
            $table->string('source_name');
            $table->string('course_slug')->nullable(); // relevance tie-in
            $table->string('note')->nullable();
            $table->date('published_on');
            $table->foreignId('added_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'published_on']);
        });

        Schema::create('pulse_digests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->date('digest_date');
            $table->text('narrative');
            $table->json('item_ids');
            $table->string('source'); // ai | fallback
            $table->foreignId('ai_event_id')->nullable()->constrained('ai_events')->nullOnDelete();
            $table->timestamps();

            $table->unique(['tenant_id', 'digest_date']);
        });

        Schema::create('content_hub_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('kind'); // youtube | instagram | podcast
            $table->string('title');
            $table->string('url');
            $table->string('source')->default('manual'); // manual | rss | api
            $table->timestamp('published_at');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'published_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_hub_items');
        Schema::dropIfExists('pulse_digests');
        Schema::dropIfExists('pulse_items');
        Schema::dropIfExists('celebration_guidances');
        Schema::dropIfExists('celebrations');
    }
};
