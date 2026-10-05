<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Employer module F18 — who actually runs a "human" round for this JD.
 *
 * Nullable and set-on-delete-null rather than required: a round can exist
 * before anyone is assigned (the employer is prompted to add a teammate
 * first), and a member leaving the workspace should not take the round's
 * history with them — it just needs a new assignee.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employer_job_rounds', function (Blueprint $table): void {
            $table->foreignId('assigned_member_id')
                ->nullable()
                ->after('kind')
                ->constrained('employer_members')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('employer_job_rounds', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('assigned_member_id');
        });
    }
};
