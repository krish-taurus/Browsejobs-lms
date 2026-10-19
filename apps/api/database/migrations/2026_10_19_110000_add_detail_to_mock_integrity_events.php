<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The numbers behind a lip-sync or voice finding (seconds, delay in ms, share of speech
     * with moving lips). Numbers only: the report words them itself, so nothing typed by a
     * browser ever reaches an employer's PDF.
     */
    public function up(): void
    {
        Schema::table('mock_integrity_events', function (Blueprint $table): void {
            $table->json('detail')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('mock_integrity_events', function (Blueprint $table): void {
            $table->dropColumn('detail');
        });
    }
};
