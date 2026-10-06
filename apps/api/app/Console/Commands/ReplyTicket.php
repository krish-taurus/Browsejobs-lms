<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Support\PostTicketReply;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

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
        {--as= : Staff email to reply as}
        {--as-name= : Display name of that staff member}';

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

    /**
     * Who the student sees the reply from.
     *
     * The old fallback took "the tenant's first staff user", which was a seeded
     * demo counsellor — so a reply typed by Priyanka reached the student signed
     * "Aisha Rahman", an account that is not a real colleague. Staff work in the
     * CRM and do not all have LMS logins, so rather than guess, an unknown
     * replier is created as a staff account from the identity the CRM sends.
     * Attribution is then correct for ever, with no manual account setup.
     */
    private function author(Ticket $ticket): ?User
    {
        $email = trim((string) $this->option('as'));
        $name = trim((string) $this->option('as-name'));

        $staff = fn () => User::query()->withoutGlobalScopes()
            ->where('tenant_id', $ticket->tenant_id)
            ->where('user_type', '!=', 'student');

        if ($email !== '') {
            $match = $staff()->where('email', $email)->first();

            if ($match !== null) {
                return $match;
            }

            return User::query()->create([
                'tenant_id' => $ticket->tenant_id,
                'name' => $name !== '' ? $name : Str::of(Str::before($email, '@'))->replace(['.', '_'], ' ')->title()->toString(),
                'email' => $email,
                'user_type' => 'staff',
                // They sign in to the CRM, never here; a random secret keeps the
                // account unusable as a login while making the name real.
                'password' => Hash::make(Str::random(40)),
            ]);
        }

        // No identity at all (a scheduled job, say): never sign as a demo seed.
        return $staff()->where('email', 'not like', '%@%.test')->orderBy('id')->first()
            ?? $staff()->orderBy('id')->first();
    }
}
