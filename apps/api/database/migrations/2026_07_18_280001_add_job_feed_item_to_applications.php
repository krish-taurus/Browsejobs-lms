<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Apply Assist (PRD §6.22): the tracker holds external feed-item applications too,
 * not just internal postings. `job_posting_id` becomes nullable and a nullable
 * `job_feed_item_id` links the feed origin, deduped per student.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_applications', function (Blueprint $table): void {
            $table->unsignedBigInteger('job_posting_id')->nullable()->change();
            $table->foreignId('job_feed_item_id')->nullable()->after('job_posting_id')->constrained()->nullOnDelete();
            $table->unique(['job_feed_item_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::table('job_applications', function (Blueprint $table): void {
            $table->dropUnique(['job_feed_item_id', 'user_id']);
            $table->dropConstrainedForeignId('job_feed_item_id');
        });
    }
};
