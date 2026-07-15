<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index('tenant_id');
            $table->unique('user_id');
        });

        Schema::create('support_teams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            // Maps to a support concern category (PRD §6.13) for ticket routing.
            $table->string('category');
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index('tenant_id');
            $table->unique(['tenant_id', 'slug']);
        });

        Schema::create('support_team_user', function (Blueprint $table) {
            $table->foreignId('support_team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['support_team_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_team_user');
        Schema::dropIfExists('support_teams');
        Schema::dropIfExists('staff_profiles');
    }
};
