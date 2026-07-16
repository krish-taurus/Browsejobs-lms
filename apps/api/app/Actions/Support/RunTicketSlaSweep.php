<?php

declare(strict_types=1);

namespace App\Actions\Support;

use App\Enums\TicketStatus;
use App\Jobs\CheckTicketSla;
use App\Models\Ticket;
use Illuminate\Support\Carbon;

/**
 * Daily safety net for the ticket SLA (PRD §6.13). Re-checks active tickets whose
 * first-response or resolution due-time has passed but was never flagged (e.g.
 * after downtime), dispatching the idempotent CheckTicketSla job for each.
 *
 * @phpstan-type SweepSummary array{swept: int}
 */
final readonly class RunTicketSlaSweep
{
    /**
     * @return SweepSummary
     */
    public function handle(?Carbon $now = null): array
    {
        $now ??= now();
        $active = array_map(fn (TicketStatus $s) => $s->value, TicketStatus::active());

        $ids = Ticket::query()
            ->withoutGlobalScopes()
            ->whereIn('status', $active)
            ->where(function ($q) use ($now) {
                $q->where(function ($x) use ($now) {
                    $x->whereNull('first_response_at')->whereNull('breached_at')
                        ->whereNotNull('first_response_due_at')->where('first_response_due_at', '<=', $now);
                })->orWhere(function ($x) use ($now) {
                    $x->whereNull('resolution_breached_at')
                        ->whereNotNull('resolution_due_at')->where('resolution_due_at', '<=', $now);
                });
            })
            ->pluck('id');

        foreach ($ids as $id) {
            CheckTicketSla::dispatch($id, 'first_response');
            CheckTicketSla::dispatch($id, 'resolution');
        }

        return ['swept' => $ids->count()];
    }
}
