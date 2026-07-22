<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-module trainer allocation for a batch (PRD §6.3). A batch can be taught by
 * several trainers split by module — e.g. Python by one, PySpark by another. The
 * batch's `trainer_id` stays the lead/default; a row here overrides it for a
 * specific module, so each class is briefed and notified to the right trainer.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('batch_module_trainers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('batch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('module_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete(); // the trainer
            $table->timestamps();

            $table->unique(['batch_id', 'module_id']); // one trainer per module per batch
            $table->index(['tenant_id', 'batch_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('batch_module_trainers');
    }
};
