<?php

declare(strict_types=1);

namespace App\Actions\Support;

use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Models\TicketMessage;
use Illuminate\Http\UploadedFile;

/**
 * Persists uploaded ticket files to s3 under tickets/{tenant_id}/… and records a
 * TicketAttachment per file (PRD §6.13 attachments). Same tenant-prefixed
 * convention as receipts/recordings/testimonials.
 */
final readonly class StoreTicketAttachments
{
    /**
     * @param  list<UploadedFile>  $files
     */
    public function handle(Ticket $ticket, TicketMessage $message, array $files): void
    {
        foreach ($files as $file) {
            $path = $file->store("tickets/{$ticket->tenant_id}", 's3');
            if ($path === false) {
                continue;
            }

            TicketAttachment::query()->create([
                'tenant_id' => $ticket->tenant_id,
                'ticket_id' => $ticket->id,
                'ticket_message_id' => $message->id,
                'path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime' => $file->getClientMimeType(),
                'size_bytes' => $file->getSize() ?: 0,
            ]);
        }
    }
}
