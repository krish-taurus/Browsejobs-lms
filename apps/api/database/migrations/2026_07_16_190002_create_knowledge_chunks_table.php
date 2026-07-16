<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Retrievable chunks of a knowledge document (PRD §6.4). Lexical retrieval ranks
     * these in PHP (SQLite-safe, no FULLTEXT); `embedding` is left nullable so
     * semantic search can drop in later without a reschema.
     */
    public function up(): void
    {
        Schema::create('knowledge_chunks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_id')->constrained('knowledge_documents')->cascadeOnDelete();
            $table->unsignedInteger('ordinal');
            $table->text('content');
            $table->unsignedInteger('token_estimate')->default(0);
            $table->unsignedInteger('term_count')->default(0);   // ranking denominator
            $table->json('embedding')->nullable();               // future phase — left null
            $table->timestamps();

            $table->index('tenant_id');
            $table->index(['tenant_id', 'document_id', 'ordinal']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_chunks');
    }
};
