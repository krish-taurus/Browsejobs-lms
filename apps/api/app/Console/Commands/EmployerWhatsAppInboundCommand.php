<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Employers\ReceiveEmployerWhatsApp;
use Illuminate\Console\Command;

/**
 * Bridge from the CRM, which receives every WhatsApp message on the shared
 * business number: hands one inbound message to the hiring chat. Prints one
 * word — queued, duplicate or not_employer — so the CRM knows whether to
 * keep handling the message itself. Fast and AI-free; the reply is queued.
 */
final class EmployerWhatsAppInboundCommand extends Command
{
    protected $signature = 'employer:whatsapp-inbound
        {from : Sender phone, digits with country code}
        {--text= : The message text}
        {--id= : WhatsApp message id (wamid), used to drop retries}
        {--name= : WhatsApp profile name}';

    protected $description = 'Route one inbound WhatsApp message to the employer hiring chat (called by the CRM).';

    public function handle(ReceiveEmployerWhatsApp $receive): int
    {
        $result = $receive->handle(
            (string) $this->argument('from'),
            (string) $this->option('text'),
            $this->option('id') !== null ? (string) $this->option('id') : null,
            $this->option('name') !== null ? (string) $this->option('name') : null,
        );

        $this->line($result);

        return self::SUCCESS;
    }
}
