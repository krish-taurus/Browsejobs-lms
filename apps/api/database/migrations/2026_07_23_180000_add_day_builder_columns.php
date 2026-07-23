<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Day-by-day syllabus builder (ADR 0049). A topic can now be a numbered
 * teaching day inside its module, carrying the keywords the trainer picked
 * (which drive AI generation of the day plan, the simple-notes PDF, and the
 * MCQ assignment) plus a short human summary of what the day covers.
 * Quizzes gain a PDF artifact so a day's assignment can be downloaded as a
 * paper — AI-rendered or manually uploaded by a mentor.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('topics', function (Blueprint $table): void {
            $table->unsignedSmallInteger('day_number')->nullable()->after('position');
            $table->json('keywords')->nullable()->after('day_number');
            $table->text('summary')->nullable()->after('keywords');

            $table->index(['module_id', 'day_number']);
        });

        Schema::table('quizzes', function (Blueprint $table): void {
            $table->string('pdf_path')->nullable()->after('source');
            $table->boolean('pdf_uploaded')->default(false)->after('pdf_path');
        });
    }

    public function down(): void
    {
        Schema::table('topics', function (Blueprint $table): void {
            $table->dropIndex(['module_id', 'day_number']);
            $table->dropColumn(['day_number', 'keywords', 'summary']);
        });

        Schema::table('quizzes', function (Blueprint $table): void {
            $table->dropColumn(['pdf_path', 'pdf_uploaded']);
        });
    }
};
