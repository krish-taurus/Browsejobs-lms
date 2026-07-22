<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Flashcards attached to a lesson (PRD §6 learning loop). Generated from the
 * lesson's material or written by hand; a card in this table is published (the
 * admin's save is the human-in-the-loop gate). Students review them on an SM-2
 * spaced schedule (see flashcard_reviews).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('flashcards', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lesson_id')->constrained()->cascadeOnDelete();
            $table->text('front');
            $table->text('back');
            $table->string('source')->default('manual'); // manual | ai
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->index(['tenant_id', 'lesson_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('flashcards');
    }
};
