<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-student channel preferences (PRD §6.9). `preferred_channel` picks the
     * default delivery lane; `marketing_opt_in` gates promotional-category sends
     * (DPDP / WABA — utility messages don't need it).
     */
    public function up(): void
    {
        Schema::create('message_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('preferred_channel')->default('whatsapp');
            $table->boolean('marketing_opt_in')->default(false);
            $table->timestamps();

            $table->index('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_preferences');
    }
};
