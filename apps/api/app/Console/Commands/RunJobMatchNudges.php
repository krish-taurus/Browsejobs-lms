<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Models\User;
use App\Support\JobFeed\JobsForYou;
use App\Support\Messaging\Messenger;
use App\Support\Tenancy\TenantContext;
use Illuminate\Console\Command;

/**
 * Daily "Jobs for You" nudge (PRD §6.22): tells students how many new roles
 * ingested in the last day are a strong match ("3 new Data Engineer roles match
 * you today"). Best-effort via the messaging hub — no template, no send.
 */
final class RunJobMatchNudges extends Command
{
    protected $signature = 'jobs:nudge';

    protected $description = 'Notify students of new well-matched roles in their feed';

    private const STRONG_MATCH = 50;

    public function handle(JobsForYou $feed, Messenger $messenger): int
    {
        $nudged = 0;

        foreach (Tenant::query()->where('status', 'active')->get() as $tenant) {
            app(TenantContext::class)->run($tenant, function () use ($tenant, $feed, $messenger, &$nudged): void {
                $students = User::query()
                    ->where('tenant_id', $tenant->id)
                    ->where('user_type', 'student')
                    ->get();

                foreach ($students as $student) {
                    $matches = $feed->for($student, ['min_match' => self::STRONG_MATCH, 'since_hours' => 24]);
                    if ($matches === []) {
                        continue;
                    }

                    $messenger->send($student, 'jobs.nudge', [
                        'name' => (string) $student->name,
                        'count' => (string) count($matches),
                        'role' => (string) ($matches[0]['item']->role_title ?? 'new'),
                    ]);
                    $nudged++;
                }
            });
        }

        $this->info("Nudged {$nudged} student(s) about new matches.");

        return self::SUCCESS;
    }
}
