<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Widen the curated news table beyond funding: hiring announcements and
// reported layoffs share the same sourced-item shape and feed the daily brief.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('funding_news', function (Blueprint $table) {
            $table->string('kind', 16)->default('funding')->after('id'); // funding | hiring | layoff
            $table->string('headline', 200)->nullable()->after('company');
            $table->index(['kind', 'active', 'published_on']);
        });
    }

    public function down(): void
    {
        Schema::table('funding_news', function (Blueprint $table) {
            $table->dropIndex(['kind', 'active', 'published_on']);
            $table->dropColumn(['kind', 'headline']);
        });
    }
};
