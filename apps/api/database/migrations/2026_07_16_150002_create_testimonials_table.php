<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Student-submitted testimonials (PRD §5 Stage 3). Lands `pending`; an admin
     * verifies before it publishes to the /reviews wall (a `reviews` row) and a
     * voucher is issued. `voucher_issue_id` is an indexed column with no DB FK to
     * avoid a circular constraint with `voucher_issues.testimonial_id`.
     */
    public function up(): void
    {
        Schema::create('testimonials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();      // student
            $table->foreignId('batch_id')->nullable()->constrained()->nullOnDelete(); // bootcamp
            $table->string('course_slug')->nullable();
            $table->unsignedTinyInteger('rating');
            $table->text('body');
            $table->string('video_path')->nullable();
            $table->boolean('consent_publish')->default(false);
            $table->string('status')->default('pending');                        // pending|approved|rejected
            $table->foreignId('review_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('voucher_issue_id')->nullable();          // no FK (breaks the cycle)
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('reject_reason')->nullable();
            $table->timestamps();

            $table->index('tenant_id');
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'user_id', 'batch_id']);
            $table->index('voucher_issue_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('testimonials');
    }
};
