<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Alumni 6/12-month post-placement check-ins (PRD §6.21). Long-term outcome
 * data → proof stats + referral pipeline. Auto-scheduled from a placement's
 * `placed_at`; the alumnus responds in-portal. CTC captured in paise.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alumni_checkins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('job_application_id')->nullable()->constrained()->nullOnDelete();
            $table->string('milestone'); // m6 | m12
            $table->string('status')->default('scheduled'); // scheduled | responded
            $table->date('scheduled_for');
            $table->timestamp('responded_at')->nullable();
            $table->boolean('still_employed')->nullable();
            $table->string('current_company')->nullable();
            $table->unsignedBigInteger('current_ctc_paise')->nullable();
            $table->boolean('would_refer')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'job_application_id', 'milestone']);
            $table->index('tenant_id');
            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alumni_checkins');
    }
};
