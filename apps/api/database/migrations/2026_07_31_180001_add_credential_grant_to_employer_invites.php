<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marks an invite that may also set the account's first password (PRD-E F1).
 *
 * Ops onboards an employer from the admin panel, which creates the owner's
 * account without a usable password — nobody at BrowseJobs ever chooses a
 * customer's credential. The employer sets their own from the invite link.
 *
 * That power is bounded by this flag rather than granted to every invite.
 * It is set only when the onboarding action created the account, so a token
 * can add someone to a workspace but can never overwrite the password of an
 * account that already had one. Without the flag, an invite emailed to an
 * address that happens to match an existing user would be an account
 * takeover.
 *
 * Default false: every invite issued before this migration was a plain team
 * invite and must stay one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employer_invites', function (Blueprint $table): void {
            $table->boolean('grants_credential')->default(false)->after('role');
        });
    }

    public function down(): void
    {
        Schema::table('employer_invites', function (Blueprint $table): void {
            $table->dropColumn('grants_credential');
        });
    }
};
