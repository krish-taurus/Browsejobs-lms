<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\LiveClasses\CancelLiveSession;
use App\Enums\BatchType;
use App\Enums\LiveSessionStatus;
use App\Models\Batch;
use App\Models\Scopes\TenantScope;
use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use Illuminate\Console\Command;

/**
 * Cancels a masterclass: cancels its scheduled class (Zoom deleted, every
 * enrolled student + batch staff notified, reminder ladder voided) and marks
 * the batch cancelled. The weekend generator then leaves that course+date
 * alone and re-invites the attendees to the next masterclass instead.
 */
final class CancelMasterclass extends Command
{
    protected $signature = 'masterclass:cancel
        {batch : Batch id or number}
        {--reason=Masterclass cancelled : Shown in the notification}';

    protected $description = 'Cancel a masterclass batch and its class, notifying everyone enrolled';

    public function handle(CancelLiveSession $cancel, TenantContext $tenants): int
    {
        $batch = Batch::query()->withoutGlobalScope(TenantScope::class)
            ->where('type', BatchType::Masterclass->value)
            ->where(fn ($q) => is_numeric($key = (string) $this->argument('batch'))
                ? $q->whereKey((int) $key)->orWhere('number', $key)
                : $q->where('number', $key))
            ->first();

        if ($batch === null) {
            $this->error("Masterclass batch '{$this->argument('batch')}' not found.");

            return self::FAILURE;
        }

        $tenant = Tenant::query()->find($batch->tenant_id);

        return $tenants->run($tenant, function () use ($batch, $cancel): int {
            $sessions = $batch->liveSessions()
                ->where('status', LiveSessionStatus::Scheduled->value)
                ->get();

            foreach ($sessions as $session) {
                $cancel->handle($session, (string) $this->option('reason'));
            }

            $batch->update(['status' => 'cancelled']);

            $this->info("Cancelled {$batch->number} ({$sessions->count()} class(es) cancelled, batch marked cancelled).");

            return self::SUCCESS;
        });
    }
}
