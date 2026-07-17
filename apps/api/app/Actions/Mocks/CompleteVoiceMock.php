<?php

declare(strict_types=1);

namespace App\Actions\Mocks;

use App\Enums\AiEventStatus;
use App\Enums\AiPurpose;
use App\Enums\EntitlementFeature;
use App\Models\AiEvent;
use App\Models\MockInterview;
use App\Models\MockTurn;
use App\Models\Tenant;
use App\Support\Entitlements\EntitlementService;
use App\Support\Tenancy\TenantContext;

/**
 * End-of-call handling for voice mocks (PRD §6.6): provider transcript →
 * mock_turns → the P4.1 scorecard pipeline (points, badge, PRI blend all
 * ride along). Sessions with too little candidate signal are ABANDONED and
 * the credit refunded — nobody pays for a dropped call. Per-session provider
 * cost lands in ai_events for margin tracking. Idempotent per delivery.
 */
final readonly class CompleteVoiceMock
{
    public function __construct(
        private FinishMockInterview $finish,
        private EntitlementService $entitlements,
    ) {}

    /**
     * @param  list<array{role: string, text: string}>  $transcript
     */
    public function handle(
        string $sessionId,
        array $transcript,
        int $durationSeconds,
        int $costMicros,
        bool $failed = false,
    ): ?MockInterview {
        $interview = MockInterview::query()->withoutGlobalScopes()
            ->where('provider_session_id', $sessionId)
            ->first();

        if ($interview === null || $interview->status !== MockInterview::STATUS_IN_PROGRESS) {
            return $interview; // Unknown session or a repeat delivery.
        }

        $tenant = Tenant::query()->find($interview->tenant_id);
        if ($tenant === null) {
            return null;
        }

        return app(TenantContext::class)->run($tenant, function () use ($interview, $transcript, $durationSeconds, $costMicros, $failed): MockInterview {
            $interview->update([
                'duration_seconds' => $durationSeconds,
                'cost_micros' => $costMicros,
            ]);

            $this->logCost($interview, $durationSeconds, $costMicros);

            foreach ($transcript as $entry) {
                $body = trim((string) ($entry['text'] ?? ''));
                if ($body === '') {
                    continue;
                }

                MockTurn::query()->create([
                    'tenant_id' => $interview->tenant_id,
                    'mock_interview_id' => $interview->id,
                    'role' => in_array($entry['role'] ?? '', ['assistant', 'bot', 'interviewer'], true)
                        ? MockTurn::ROLE_INTERVIEWER
                        : MockTurn::ROLE_CANDIDATE,
                    'body' => $body,
                ]);
            }

            $enough = ! $failed
                && $interview->candidateAnswers() >= (int) config('mocks.min_answers', 2);

            if (! $enough) {
                $interview->update(['status' => MockInterview::STATUS_ABANDONED, 'completed_at' => now()]);
                $this->entitlements->grantCredits(
                    $interview->student,
                    EntitlementFeature::VoiceMock->value,
                    1,
                    "voice_refund:{$interview->id}",
                );

                return $interview->refresh();
            }

            return $this->finish->handle($interview);
        });
    }

    /** Provider spend per session → ai_events, same ledger as token costs. */
    private function logCost(MockInterview $interview, int $durationSeconds, int $costMicros): void
    {
        AiEvent::query()->create([
            'tenant_id' => $interview->tenant_id,
            'user_id' => $interview->user_id,
            'purpose' => AiPurpose::Mock->value,
            'model' => 'voice-session',
            'prompt_tokens' => 0,
            'completion_tokens' => 0,
            'total_tokens' => 0,
            'cost_micros' => $costMicros,
            'latency_ms' => 0,
            'status' => AiEventStatus::Ok->value,
            'meta' => [
                'mode' => 'voice',
                'session_id' => $interview->provider_session_id,
                'duration_seconds' => $durationSeconds,
            ],
        ]);
    }
}
