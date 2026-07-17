<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Support-desk AI deflection (PRD §6.13, P3.5d-a) answers from a `support` corpus —
 * fee/refund/reschedule policy documents authored by staff, sitting in the same
 * `knowledge_documents` table the tutor already retrieves from (`source_id` is
 * already nullable, so a policy article needs no synthetic source row).
 *
 * `category` maps a support document to a `TicketCategory` so a payments question
 * retrieves payments policy rather than course material. Null for every non-support
 * document — the tutor's corpus is uncategorised and stays that way.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('knowledge_documents', function (Blueprint $table) {
            $table->string('category')->nullable()->after('source_id');
            $table->index(['tenant_id', 'source_type', 'category']);
        });
    }

    public function down(): void
    {
        Schema::table('knowledge_documents', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'source_type', 'category']);
            $table->dropColumn('category');
        });
    }
};
