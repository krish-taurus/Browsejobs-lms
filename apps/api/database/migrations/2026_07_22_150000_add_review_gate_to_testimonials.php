<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Review-gate + social-attest fields on testimonials (Platform Spec §3). `stage`
 * records which funnel moment prompted the review. The three self-attest flags
 * capture that the candidate says they followed us / posted on Google — an admin
 * confirms before the voucher issues (no API can verify a follow or a Google
 * post). A rating below the public threshold lands as `private_feedback` and is
 * never published, so no new column is needed for the gate itself.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('testimonials', function (Blueprint $table): void {
            $table->string('stage')->nullable()->after('course_slug');
            $table->boolean('followed_instagram')->default(false)->after('consent_publish');
            $table->boolean('subscribed_youtube')->default(false)->after('followed_instagram');
            $table->boolean('posted_google_review')->default(false)->after('subscribed_youtube');
        });
    }

    public function down(): void
    {
        Schema::table('testimonials', function (Blueprint $table): void {
            $table->dropColumn(['stage', 'followed_instagram', 'subscribed_youtube', 'posted_google_review']);
        });
    }
};
