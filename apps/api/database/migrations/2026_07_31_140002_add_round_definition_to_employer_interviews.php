<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Links an interview to the round definition it was sent from (F18), and
 * records the round's name at invite time.
 *
 * The name is denormalised on purpose. `question_set` is already a snapshot
 * so a later edit cannot change what a candidate was asked; the label the
 * candidate saw belongs in that same snapshot, or renaming a round would
 * silently rewrite history for every interview already sat.
 *
 * Both columns are nullable: every interview created before this migration
 * predates round definitions, and inventing a definition for them would be
 * fabricating a record.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employer_interviews', function (Blueprint $table): void {
            $table->foreignId('employer_job_round_id')
                ->nullable()
                ->after('employer_job_application_id')
                ->constrained('employer_job_rounds', indexName: 'ei_round_fk')
                ->nullOnDelete();

            $table->string('round_name')->nullable()->after('round');
        });
    }

    public function down(): void
    {
        Schema::table('employer_interviews', function (Blueprint $table): void {
            $table->dropForeign('ei_round_fk');
            $table->dropColumn(['employer_job_round_id', 'round_name']);
        });
    }
};
