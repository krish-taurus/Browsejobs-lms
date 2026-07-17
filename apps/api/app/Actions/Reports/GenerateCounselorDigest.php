<?php

declare(strict_types=1);

namespace App\Actions\Reports;

use App\Enums\ReportType;
use App\Models\InAppNotification;
use App\Models\Report;
use App\Models\User;
use App\Support\Messaging\Messenger;
use App\Support\Reports\ReportNarrator;
use App\Support\Tenancy\TenantContext;

/**
 * Generates a counselor's daily risk digest (PRD §6.10): narrates the risk movers +
 * intervention scripts (billed to the counselor, fail-safe) and delivers it. Skips
 * gracefully when there is nothing to act on. Idempotent per day.
 */
final readonly class GenerateCounselorDigest
{
    public function __construct(
        private BuildCounselorDigest $build,
        private ReportNarrator $narrator,
        private Messenger $messenger,
    ) {}

    public function handle(User $counselor): ?Report
    {
        return app(TenantContext::class)->run($counselor->tenant, function () use ($counselor): ?Report {
            $digest = $this->build->handle();

            if ($digest['high_risk'] === 0 && $digest['movers'] === []) {
                return null; // nothing urgent today — no digest
            }

            $movers = collect($digest['movers'])
                ->map(fn (array $m) => "{$m['name']} (+{$m['mover']})")
                ->implode(', ');
            $scripts = collect($digest['movers'])
                ->take(3)
                ->map(fn (array $m) => "{$m['name']}: {$m['script']}")
                ->implode(' | ');

            $narration = $this->narrator->narrate($counselor, ReportType::CounselorDigest, [
                'name' => $counselor->name,
                'high_risk_count' => (string) $digest['high_risk'],
                'movers' => $movers,
                'scripts' => $scripts,
            ]);

            $report = Report::query()->updateOrCreate(
                ['recipient_id' => $counselor->id, 'type' => ReportType::CounselorDigest->value, 'period_start' => now()->startOfDay()],
                [
                    'tenant_id' => $counselor->tenant_id,
                    'title' => 'Daily risk digest — '.now()->format('j M'),
                    'narrative' => $narration['narrative'],
                    'data' => $digest,
                    'period_end' => now()->startOfDay(),
                    'ai_event_id' => $narration['ai_event_id'],
                ],
            );

            if ($report->wasRecentlyCreated) {
                $this->messenger->send($counselor, 'counselor_digest', [
                    'name' => $counselor->name,
                    'body' => $narration['narrative'],
                ]);

                InAppNotification::query()->create([
                    'tenant_id' => $counselor->tenant_id,
                    'user_id' => $counselor->id,
                    'title' => 'Daily risk digest',
                    'body' => $narration['narrative'],
                    'url' => '/admin/risk',
                ]);
            }

            return $report;
        });
    }
}
