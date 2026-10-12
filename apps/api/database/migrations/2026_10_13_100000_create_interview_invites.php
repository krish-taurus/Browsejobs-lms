<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hiring steps 5–6: the message agent and spoken, recorded L1/L2 rounds.
 *
 * - interview_invites: one per (application, round). The message agent
 *   WhatsApps the candidate three dates; their reply books one; on that day
 *   the round opens and they get a link (/hire-interview/{token}).
 * - mock_blueprints.employer_job_round_id: a round is taken in the voice
 *   interview room (recorded, scored). Its blueprint is tied to the round,
 *   NOT to employer_job_id, so it can never count as the JD mock a
 *   candidate needs to apply.
 * - employer_interviews.mock_interview_id: the voice interview that scored it.
 * - employer_candidates.user_id: client/Naukri candidates get a light
 *   account (phone + OTP) so they can sit the interview; the CRM's lead
 *   import skips these accounts.
 * - employer_whatsapp_messages.interview_invite_id: a candidate's reply.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('interview_invites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employer_job_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employer_job_application_id')->constrained()->cascadeOnDelete();
            $table->foreignId('candidate_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('employer_interview_id')->nullable()->constrained()->nullOnDelete();
            $table->string('round', 20)->default('l1');
            $table->string('phone', 20);
            $table->string('status', 20)->default('pending');
            // pending → sent (asked for a date) → scheduled (date picked) → open (link sent) → done
            //   or declined / failed
            $table->json('date_options')->nullable();
            $table->date('chosen_date')->nullable();
            $table->string('token', 64)->unique();
            $table->string('wamid', 191)->nullable();
            $table->string('error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('replied_at')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->foreignId('sent_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['employer_job_application_id', 'round']);
            $table->index(['phone', 'status']);
            $table->index(['status', 'chosen_date']);
        });

        Schema::table('mock_blueprints', function (Blueprint $table) {
            $table->foreignId('employer_job_round_id')->nullable()->after('employer_job_id')->constrained()->nullOnDelete();
        });

        Schema::table('employer_interviews', function (Blueprint $table) {
            $table->foreignId('mock_interview_id')->nullable()->after('employer_job_round_id')->constrained()->nullOnDelete();
        });

        Schema::table('employer_candidates', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('employer_candidate_import_id')->constrained()->nullOnDelete();
        });

        Schema::table('employer_whatsapp_messages', function (Blueprint $table) {
            $table->foreignId('interview_invite_id')->nullable()->after('employer_whatsapp_link_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('employer_whatsapp_messages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('interview_invite_id');
        });
        Schema::table('employer_candidates', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
        });
        Schema::table('employer_interviews', function (Blueprint $table) {
            $table->dropConstrainedForeignId('mock_interview_id');
        });
        Schema::table('mock_blueprints', function (Blueprint $table) {
            $table->dropConstrainedForeignId('employer_job_round_id');
        });
        Schema::dropIfExists('interview_invites');
    }
};
