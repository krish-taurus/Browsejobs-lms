<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\Commands\Concerns\ResolvesCrmTargets;
use App\Enums\BatchMemberStatus;
use App\Enums\MessageChannel;
use App\Support\Messaging\Messenger;
use Illuminate\Console\Command;

/**
 * Sends a one-off announcement to every occupying student of a batch on
 * WhatsApp + email (batch_announcement template) — driven by the CRM's
 * "Message this batch" form.
 */
final class BatchAnnounce extends Command
{
    use ResolvesCrmTargets;

    protected $signature = 'batch:announce
        {batch : Batch id or number}
        {message : The announcement text}
        {--subject=Batch update : Email subject line}';

    protected $description = 'Announce a message to every student in a batch (WhatsApp + email)';

    public function handle(Messenger $messenger): int
    {
        $batch = $this->findBatch((string) $this->argument('batch'));

        if ($batch === null) {
            $this->error("Batch '{$this->argument('batch')}' not found.");

            return self::FAILURE;
        }

        return $this->runForTenant($batch->tenant_id, function () use ($batch, $messenger): int {
            $occupying = array_map(fn (BatchMemberStatus $s) => $s->value, BatchMemberStatus::occupying());
            $members = $batch->members()->whereIn('status', $occupying)->with('student')->get();

            $sent = 0;

            foreach ($members as $member) {
                if ($member->student === null) {
                    continue;
                }

                $vars = [
                    'name' => $member->student->name,
                    'batch' => $batch->number,
                    'subject' => (string) $this->option('subject'),
                    'message' => (string) $this->argument('message'),
                ];

                foreach ([MessageChannel::WhatsApp, MessageChannel::Email] as $channel) {
                    $messenger->send($member->student, 'batch_announcement', $vars, ['channel' => $channel]);
                }

                $sent++;
            }

            $this->info("Announcement sent to {$sent} student(s) of {$batch->number} on WhatsApp + email.");

            return self::SUCCESS;
        });
    }
}
