<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Inbound provider webhook audit log (PRD §8 `webhooks_log`). Every Razorpay
     * delivery is recorded with its signature-validity and payload for
     * reconciliation + debugging. Tenant is nullable (resolved from the payload).
     */
    public function up(): void
    {
        Schema::create('webhooks_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider');
            $table->string('event')->nullable();
            $table->string('event_id')->nullable();
            $table->boolean('signature_valid')->default(false);
            $table->json('payload')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['provider', 'event']);
            $table->index('event_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhooks_log');
    }
};
