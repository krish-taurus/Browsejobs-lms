<?php

declare(strict_types=1);

namespace App\Actions\Reports;

use App\Enums\ReportType;
use App\Models\InAppNotification;
use App\Models\Report;
use App\Models\User;
use App\Support\Reports\ReportNarrator;
use App\Support\Tenancy\TenantContext;

/**
 * The weekly "top concern themes" report to admin (PRD §6.13). Mirrors
 * GenerateCounselorDigest: aggregate → narrate (billed to the admin, fail-safe) →
 * persist → notify. Idempotent per admin per week.
 *
 * In-app only. The counselor digest also messages on WhatsApp because a student at risk
 * is time-critical; a themes report is a Monday-morning read, and paging an admin about
 * last week's ticket mix would train them to ignore the channel that carries the
 * urgent things.
 */
final readonly class GenerateSupportThemes
{
    public function __construct(
        private BuildSupportThemes $build,
        private ReportNarrator $narrator,
    ) {}

    public function handle(User $admin): ?Report
    {
        return app(TenantContext::class)->run($admin->tenant, function () use ($admin): ?Report {
            $from = now()->subWeek()->startOfDay();
            $to = now()->startOfDay();

            $themes = $this->build->handle($from, $to);

            if ($themes['total'] === 0) {
                return null; // no tickets, no themes — silence is the honest report
            }

            $narration = $this->narrator->narrate($admin, ReportType::SupportThemes, [
                'name' => $admin->name,
                'period' => $from->format('j M').' – '.$to->format('j M'),
                'total' => (string) $themes['total'],
                'by_category' => $this->renderCategories($themes['by_category']),
                'distressed' => (string) $themes['distressed'],
                'duplicates' => (string) $themes['duplicates'],
                'breached' => (string) $themes['breached'],
                'csat_avg' => $themes['csat_avg'] === null ? 'nobody rated a ticket' : (string) $themes['csat_avg'].' out of 5',
                'deflection' => $this->renderDeflection($themes['deflection']),
            ]);

            $report = Report::query()->updateOrCreate(
                ['recipient_id' => $admin->id, 'type' => ReportType::SupportThemes->value, 'period_start' => $from],
                [
                    'tenant_id' => $admin->tenant_id,
                    'title' => 'Support themes — week to '.$to->format('j M'),
                    'narrative' => $narration['narrative'],
                    'data' => $themes,
                    'period_end' => $to,
                    'ai_event_id' => $narration['ai_event_id'],
                ],
            );

            if ($report->wasRecentlyCreated) {
                InAppNotification::query()->create([
                    'tenant_id' => $admin->tenant_id,
                    'user_id' => $admin->id,
                    'title' => 'Weekly support themes',
                    'body' => $narration['narrative'],
                    'url' => '/admin/support',
                ]);
            }

            return $report;
        });
    }

    /** @param array<string, int> $byCategory */
    private function renderCategories(array $byCategory): string
    {
        if ($byCategory === []) {
            return 'none';
        }

        $parts = [];
        foreach ($byCategory as $category => $count) {
            $parts[] = str_replace('_', ' ', $category).' '.$count;
        }

        return implode(', ', $parts);
    }

    /** @param array<string, mixed> $deflection */
    private function renderDeflection(array $deflection): string
    {
        if ($deflection['offered'] === 0) {
            return 'no answers were offered before tickets were raised';
        }

        return $deflection['offered'].' answers offered, '.$deflection['accepted'].' accepted ('
            .$deflection['accept_pct'].'% resolved without a ticket)';
    }
}
