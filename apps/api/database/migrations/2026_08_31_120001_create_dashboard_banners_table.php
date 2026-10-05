<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rotating promotional banners on the student dashboard (candidate request,
 * Aug 2026, "like a Flipkart banner") — BrowseJobs' own product/offer
 * promos and podcast/YouTube content, managed entirely from the CRM (see
 * App\Models\DashboardBanner there for the write-access exception, same
 * pattern as career_boost_packages/products). Images live on the same
 * DigitalOcean Spaces bucket the LMS API already uses, so a banner uploaded
 * from crm.browsejobs.ai is immediately servable on browsejobs.ai.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dashboard_banners', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->string('subtitle')->nullable();
            $table->string('image_path'); // Spaces object key, not a URL — resolved at read time.
            $table->string('link_url')->nullable(); // internal path ("/jobs-for-you") or external URL; no link = purely informational.
            $table->string('link_label')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('enabled')->default(true);
            $table->timestamps();

            $table->index(['enabled', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashboard_banners');
    }
};
