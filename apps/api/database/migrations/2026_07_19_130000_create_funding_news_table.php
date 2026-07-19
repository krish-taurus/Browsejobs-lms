<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Curated funding/hiring news items (platform-global, super-admin managed).
// Every row must carry its public source — that is what makes naming a
// company honest reporting rather than a fabricated claim (Spec §3).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('funding_news', function (Blueprint $table) {
            $table->id();
            $table->string('company', 120)->nullable(); // null = sector-level item
            $table->string('sector', 80);
            $table->string('round', 80); // "Series C", "IPO", "New GCC centre"
            $table->string('hub', 80);
            $table->unsignedTinyInteger('hiring_lag_months')->default(3);
            $table->json('roles');
            $table->json('skills');
            $table->string('source_name', 120);
            $table->string('source_url', 500)->nullable();
            $table->date('published_on');
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->index(['active', 'published_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('funding_news');
    }
};
