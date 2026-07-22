<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marks a placement story as an illustrative SAMPLE (Platform Spec §3). A sample
 * may render publicly WITHOUT consent — it is not a real person — but the UI
 * labels it clearly so no visitor is misled. Real stories still require both
 * consent and publish. Samples are replaced by real, consented stories as they
 * come in (honesty rules, CLAUDE.md).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('placement_stories', function (Blueprint $table): void {
            $table->boolean('is_sample')->default(false)->after('is_published');
        });
    }

    public function down(): void
    {
        Schema::table('placement_stories', function (Blueprint $table): void {
            $table->dropColumn('is_sample');
        });
    }
};
