<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Scoring\ComputeStudentScores;
use App\Enums\RiskBand;
use App\Models\ScoreSnapshot;
use App\Support\Scoring\ScoreCalculator;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The Coach Panel (PRD §6.4) — the student dashboard centerpiece. Computes scores
 * live (freshness) via the rule-based calculator, persists them, and returns the
 * PRI ring + trajectory, engagement, risk, streak, mastery map, went-well /
 * needs-work, the single Next Best Action, and the fee status.
 */
final class CoachController extends Controller
{
    public function __construct(
        private readonly ScoreCalculator $calculator,
        private readonly ComputeStudentScores $compute,
    ) {}

    public function show(Request $request): JsonResponse
    {
        $student = $request->user();

        return app(TenantContext::class)->run($student->tenant, function () use ($student): JsonResponse {
            $data = $this->calculator->computeFor($student);
            $this->compute->handle($student); // persist latest + today's snapshot

            $trajectory = ScoreSnapshot::query()
                ->where('user_id', $student->id)
                ->where('captured_on', '>=', now()->subDays(14)->startOfDay())
                ->orderBy('captured_on')
                ->get(['captured_on', 'pri'])
                ->map(fn (ScoreSnapshot $s) => ['on' => $s->captured_on->toDateString(), 'pri' => $s->pri])
                ->all();

            return response()->json(['data' => [
                'pri' => $data['pri'],
                'engagement' => $data['engagement'],
                'risk' => [
                    'dropout' => $data['risk_dropout'],
                    'placement' => $data['risk_placement'],
                    'dropout_band' => RiskBand::fromScore($data['risk_dropout'])->value,
                ],
                'streak_days' => $data['streak_days'],
                'mastery' => $data['mastery'],
                'went_well' => $data['went_well'],
                'needs_work' => $data['needs_work'],
                'next_action' => $data['next_action'],
                'trajectory' => $trajectory,
            ]]);
        });
    }
}
