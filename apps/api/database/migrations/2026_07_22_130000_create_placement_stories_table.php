<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-course placement stories shown on course pages (Platform Spec §3 social
 * proof): a learner's transformation, the package + company they landed, rounds
 * cleared, and a WhatsApp-style offer message (text or an uploaded screenshot).
 * Only published + consented rows are ever served publicly.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('placement_stories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->string('student_name');
            $table->string('before_label');           // "Mechanical engineer, 2 yrs no job"
            $table->string('after_role');              // "Data Engineer"
            $table->string('package_label')->nullable(); // "₹11.5 LPA" — display label, server-owned
            $table->string('company_name')->nullable();
            $table->string('company_color', 9)->nullable(); // hex for the logo chip
            $table->unsignedTinyInteger('rounds')->nullable();
            $table->text('quote')->nullable();          // the offer-update message text
            $table->string('screenshot_path')->nullable(); // uploaded WhatsApp screenshot (s3)
            $table->boolean('consent')->default(false); // student consented to public use
            $table->boolean('is_published')->default(false);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->index(['tenant_id', 'course_id', 'is_published', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('placement_stories');
    }
};
