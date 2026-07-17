<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * P4.1 (PRD §6.6): the transport-agnostic AI mock-interviewer core.
     * Voice transport + quotas arrive in P4.3; the real-interview bank in
     * P4.2 — both plug into these tables.
     */
    public function up(): void
    {
        Schema::create('mock_blueprints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->string('role_title');
            $table->json('competencies'); // list<string> scored on the card
            $table->string('opening_question', 500);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'course_id']);
        });

        Schema::create('mock_interviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('mock_blueprint_id')->constrained()->cascadeOnDelete();
            $table->string('mode')->default('text'); // voice arrives in P4.3
            $table->string('status')->default('in_progress'); // in_progress | completed
            $table->unsignedTinyInteger('overall_score')->nullable();
            $table->json('scorecard')->nullable();
            $table->string('scorecard_source')->nullable(); // ai | fallback
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'user_id', 'status']);
        });

        Schema::create('mock_turns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('mock_interview_id')->constrained()->cascadeOnDelete();
            $table->string('role'); // interviewer | candidate
            $table->text('body');
            $table->timestamps();

            $table->index(['mock_interview_id', 'id']);
        });

        Schema::table('topics', function (Blueprint $table) {
            // PRD §6.6: TopicCompleted → mock prompt, configurable per topic.
            $table->boolean('mock_enabled')->default(false)->after('position');
        });
    }

    public function down(): void
    {
        Schema::table('topics', function (Blueprint $table) {
            $table->dropColumn('mock_enabled');
        });
        Schema::dropIfExists('mock_turns');
        Schema::dropIfExists('mock_interviews');
        Schema::dropIfExists('mock_blueprints');
    }
};
