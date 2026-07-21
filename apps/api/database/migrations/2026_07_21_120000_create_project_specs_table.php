<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A `project` lesson's capstone spec (PRD §6 curriculum). One per lesson: a
 * tech stack, a details brief, and an architecture that is either uploaded or
 * AI-generated — rendered to a downloadable file on the Space.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_specs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lesson_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('summary')->nullable();
            $table->json('tech_stack')->nullable();          // list<string>
            $table->json('architecture')->nullable();        // structured: {overview, layers[], flows[]}
            $table->string('architecture_source')->default('none'); // none | ai | upload
            $table->string('architecture_path')->nullable(); // downloadable file on the s3 disk
            $table->timestamps();

            $table->index('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_specs');
    }
};
