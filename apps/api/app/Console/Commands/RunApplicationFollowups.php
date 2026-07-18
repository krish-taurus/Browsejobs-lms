<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\JobApplication;
use App\Models\Tenant;
use App\Support\Messaging\Messenger;
use App\Support\Tenancy\TenantContext;
use Illuminate\Console\Command;

/**
 * Application follow-up nudges (PRD §6.22): reminds a student to follow up on an
 * application that's been sitting ~5 days with no interview round yet ("Applied 5
 * days ago — sent a follow-up?"). Fires once via a 24h age window so daily runs
 * don't repeat. Best-effort through the messaging hub.
 */
final class RunApplicationFollowups extends Command
{
    protected $signature = 'applications:follow-ups';

    protected $description = 'Nudge students to follow up on stalled applications';

    public function handle(Messenger $messenger): int
    {
        $nudged = 0;

        foreach (Tenant::query()->where('status', 'active')->get() as $tenant) {
            app(TenantContext::class)->run($tenant, function () use ($tenant, $messenger, &$nudged): void {
                JobApplication::query()
                    ->where('tenant_id', $tenant->id)
                    ->where('status', JobApplication::STATUS_APPLIED)
                    ->whereBetween('created_at', [now()->subDays(6), now()->subDays(5)])
                    ->whereDoesntHave('rounds')
                    ->with(['student:id,name', 'posting:id,title', 'feedItem:id,title'])
                    ->get()
                    ->each(function (JobApplication $app) use ($messenger, &$nudged): void {
                        if ($app->student === null) {
                            return;
                        }

                        $messenger->send($app->student, 'application.followup', [
                            'name' => (string) $app->student->name,
                            'role' => (string) ($app->posting?->title ?? $app->feedItem?->title ?? 'the role'),
                        ]);
                        $nudged++;
                    });
            });
        }

        $this->info("Sent {$nudged} follow-up nudge(s).");

        return self::SUCCESS;
    }
}
