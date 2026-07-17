<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\ReportType;
use App\Models\BatchMember;
use App\Models\Report;
use App\Models\Tenant;
use App\Services\AI\AiClient;
use App\Services\AI\FakeAiClient;
use App\Support\Reports\ReportNarrator;
use App\Support\Scoring\ScoreCalculator;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Seeder;

/**
 * Makes the weekly report (P3.5a) demo-able: builds one weekly report for a seeded
 * student using the narrator's deterministic fallback (no live AI call during seeding).
 */
class ReportsSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::query()->where('slug', 'browsejobs')->first();
        if ($tenant === null) {
            return;
        }

        app(TenantContext::class)->run($tenant, function () use ($tenant): void {
            $member = BatchMember::query()->whereIn('status', ['enrolled', 'reserved', 'payment_pending', 'completed'])->first();
            $student = $member?->student;
            if ($student === null) {
                return;
            }

            $data = app(ScoreCalculator::class)->computeFor($student);

            // No live AI during seeding — a fake transport gives a deterministic narrative.
            $fake = new FakeAiClient;
            $fake->reply = 'You showed up consistently this week — that steadiness compounds. '
                ."\n\nStrengthen the modules flagged below with a focused practice session. "
                ."\n\nYour one focus to crack the final interview: finish your next topic and keep the streak alive.";
            app()->instance(AiClient::class, $fake);

            $narration = app(ReportNarrator::class)->narrate($student, ReportType::WeeklyStudent, [
                'name' => $student->name,
                'went_well' => implode(', ', $data['went_well']),
                'needs_work' => implode(', ', $data['needs_work']),
                'next_action' => $data['next_action']['title'] ?? '',
                'mastery_summary' => '',
            ]);

            Report::query()->updateOrCreate(
                ['recipient_id' => $student->id, 'type' => ReportType::WeeklyStudent->value, 'period_start' => now()->startOfWeek()],
                [
                    'tenant_id' => $tenant->id,
                    'title' => 'Your week — '.now()->startOfWeek()->format('j M'),
                    'narrative' => $narration['narrative'],
                    'data' => $data,
                    'period_end' => now()->endOfWeek(),
                ],
            );
        });
    }
}
