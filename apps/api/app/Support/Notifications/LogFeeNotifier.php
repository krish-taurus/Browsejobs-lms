<?php

declare(strict_types=1);

namespace App\Support\Notifications;

use App\Models\AccessBlock;
use App\Models\Instalment;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Logs dunning notifications locally (PRD §6.8). Rebound to the real messaging
 * channel in P2.4.
 */
final class LogFeeNotifier implements FeeNotifier
{
    public function reminder(User $student, Instalment $instalment, string $rung, ?string $payUrl): void
    {
        Log::info('fee.reminder', [
            'student_id' => $student->id,
            'instalment_id' => $instalment->id,
            'rung' => $rung,
            'amount_paise' => $instalment->amount_paise,
            'pay_url' => $payUrl,
        ]);
    }

    public function blocked(User $student, AccessBlock $block): void
    {
        Log::info('fee.blocked', [
            'student_id' => $student->id,
            'level' => $block->level->value,
            'block_id' => $block->id,
        ]);
    }

    public function unblocked(User $student): void
    {
        Log::info('fee.unblocked', ['student_id' => $student->id]);
    }
}
