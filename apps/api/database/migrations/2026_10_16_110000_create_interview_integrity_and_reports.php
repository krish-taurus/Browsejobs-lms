<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Interview integrity and the employer's report.
 *
 * mock_integrity_events: what the interview room noticed while a candidate
 * was answering — leaving the tab, the window losing focus, a paste, the
 * face leaving the camera, a second face, eyes off the screen — each with
 * the moment it happened and, where the camera was on, a still of the
 * candidate at that moment. A few random check-in stills are kept too, so a
 * reviewer can see it was the same person throughout. Until now the room
 * acted on these in the browser and kept nothing.
 *
 * employer_interview_reports: the PDF report written after every graded
 * round (scores, strengths, answers, integrity with the stills) plus the
 * candidate's CV, and whether they reached the client's WhatsApp yet —
 * outside WhatsApp's 24-hour window they wait for the client's next message.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mock_integrity_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('mock_interview_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 30); // tab_hidden | window_blur | paste | face_missing | multi_face | gaze_down | gaze_side | check_in
            $table->string('severity', 10); // flag | possible | info
            $table->unsignedInteger('elapsed_seconds')->nullable(); // into the interview
            $table->string('image_path')->nullable(); // private s3 still, when the camera was on
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['mock_interview_id', 'occurred_at']);
        });

        Schema::create('employer_interview_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employer_interview_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('employer_job_id')->constrained()->cascadeOnDelete();
            $table->string('pdf_path');
            $table->string('cv_path')->nullable();
            $table->unsignedInteger('flags')->default(0);
            $table->unsignedInteger('possible_flags')->default(0);
            $table->string('caption', 500);
            $table->string('status', 20)->default('pending'); // pending | sent | failed | no_chat
            $table->foreignId('employer_whatsapp_link_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['employer_whatsapp_link_id', 'status'], 'interview_reports_link_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employer_interview_reports');
        Schema::dropIfExists('mock_integrity_events');
    }
};
