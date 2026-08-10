<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Support\ChangeTicketStatus;
use App\Enums\TicketStatus;
use App\Models\Ticket;
use Illuminate\Console\Command;

/**
 * Moves a support ticket between statuses (open / in_progress /
 * waiting_on_student / resolved / closed) — driven by the CRM Support page.
 */
final class SetTicketStatus extends Command
{
    protected $signature = 'ticket:status {ticket : Ticket id} {status : New status}';

    protected $description = 'Change a support ticket status';

    public function handle(ChangeTicketStatus $change): int
    {
        $ticket = Ticket::query()->withoutGlobalScopes()->find((int) $this->argument('ticket'));

        if ($ticket === null) {
            $this->error('Ticket not found.');

            return self::FAILURE;
        }

        $status = TicketStatus::tryFrom((string) $this->argument('status'));
        if ($status === null) {
            $this->error('Unknown status. Use: '.implode(', ', array_map(fn ($c) => $c->value, TicketStatus::cases())));

            return self::FAILURE;
        }

        $change->handle($ticket, $status);

        $this->info("{$ticket->reference} is now {$status->value}.");

        return self::SUCCESS;
    }
}
