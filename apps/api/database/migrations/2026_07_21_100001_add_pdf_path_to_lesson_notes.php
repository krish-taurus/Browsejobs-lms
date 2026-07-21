<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lesson_notes', function (Blueprint $table): void {
            // S3 key of the downloadable PDF (AI-rendered from the notes, or an
            // uploaded file). Null until one is produced.
            $table->string('pdf_path')->nullable()->after('notes');
            $table->boolean('pdf_uploaded')->default(false)->after('pdf_path');
        });
    }

    public function down(): void
    {
        Schema::table('lesson_notes', function (Blueprint $table): void {
            $table->dropColumn(['pdf_path', 'pdf_uploaded']);
        });
    }
};
