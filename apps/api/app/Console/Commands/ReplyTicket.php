<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Support\PostTicketReply;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Posts a staff reply on a support ticket — driven by the CRM Support page.
 * The author resolves from --as (staff email) or falls back to the tenant's
 * first staff user, so notifications and the thread render a real sender.
 */
final class ReplyTicket extends Command
{
    protected $signature = 'ticket:reply
        {ticket : Ticket id}
        {--body= : Reply text}
        {--as= : Staff email to reply as}';

    protected $description = 'Post a staff reply on a support ticket';

    public function handle(PostTicketReply $reply): int
    {
        $ticket = Ticket::query()->withoutGlobalScopes()->find((int) $this->argument('ticket'));

        if ($ticket === null) {
            $this->error('Ticket not found.');

            return self::FAILURE;
        }

        $body = trim((string) $this->option('body'));
        if ($body === '') {
            $this->error('A reply body is required (--body=).');

            return self::FAILURE;
        }

        $author = $this->author($ticket);
        if ($author === null) {
            $this->error('No staff user found to reply as.');

            return self::FAILURE;
        }

        $reply->handle($ticket, $author, $body);

        $this->info("Reply posted on {$ticket->reference} as {$author->name} — the student is notified.");

        return self::SUCCESS;
    }

    private function author(Ticket $ticket): ?User
    {
        $email = trim((string) $this->option('as'));

        $query = User::query()->withoutGlobalScopes()
            ->where('tenant_id', $ticket->tenant_id)
            ->where('user_type', '!=', 'student');

        if ($email !== '') {
            $match = (clone $query)->where('email', $email)->first();
            if ($match !== null) {
                return $match;
            }
        }

        return $query->orderBy('id')->first();
    }
}
