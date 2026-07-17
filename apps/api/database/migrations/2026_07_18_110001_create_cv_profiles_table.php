<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P4.5a follow-up: the candidate's own CV facts — parsed from an uploaded
 * CV and/or hand-edited (experience, personal projects, education, links).
 * Merged with platform evidence at generation time; the candidate owns
 * these claims, the platform owns its own record.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cv_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->index();
            $table->foreignId('user_id')->unique();
            $table->json('data');
            $table->string('uploaded_filename')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cv_profiles');
    }
};
