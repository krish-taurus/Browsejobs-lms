<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Employer hiring leads and course learner leads. Separate from the CRM
     * `leads` pipeline, which dedupes student phones and has no company fields.
     */
    public function up(): void
    {
        Schema::create('enquiries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('type'); // employer|course
            $table->string('status')->default('new'); // new|contacted|qualified|closed
            $table->string('name');
            $table->string('email');
            $table->string('email_normalized');
            $table->string('phone');
            $table->string('phone_normalized');
            $table->string('company')->nullable();
            $table->string('company_size')->nullable();
            $table->text('roles')->nullable();
            $table->string('city')->nullable();
            $table->string('timeline')->nullable();
            $table->string('course_slug')->nullable();
            $table->string('learner_status')->nullable();
            $table->string('preferred_time')->nullable();
            $table->text('message')->nullable();
            $table->timestamp('consented_at');
            $table->string('consent_version')->default('v1');
            $table->string('utm_source')->nullable();
            $table->string('utm_medium')->nullable();
            $table->string('utm_campaign')->nullable();
            $table->string('referrer')->nullable();
            $table->string('landing_page')->nullable();
            $table->text('user_agent')->nullable();
            $table->string('ip_hash', 64)->nullable();
            $table->timestamp('notified_at')->nullable();
            $table->timestamps();

            $table->index('tenant_id');
            $table->index('type');
            $table->index('status');
            $table->index(['tenant_id', 'type']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enquiries');
    }
};
