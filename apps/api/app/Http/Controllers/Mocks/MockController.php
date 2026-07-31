<?php

declare(strict_types=1);

namespace App\Http\Controllers\Mocks;

use App\Actions\Mocks\AnswerMockInterview;
use App\Actions\Mocks\FinishMockInterview;
use App\Actions\Mocks\StartMockInterview;
use App\Actions\Mocks\StartVoiceMock;
use App\Enums\EntitlementFeature;
use App\Http\Controllers\Controller;
use App\Models\MockBlueprint;
use App\Models\MockInterview;
use App\Models\ModuleMockRequirement;
use App\Models\Product;
use App\Services\AI\AiBudgetExceeded;
use App\Support\Entitlements\EntitlementService;
use App\Support\Interviews\GapReport;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Student AI mock interviewer (PRD §6.6, text mode). Under auth:sanctum
 * without tenant.user, so every action wraps in the student's tenant context
 * and ownership is checked by student id (mirrors TutorController).
 */
final class MockController extends Controller
{
    public function __construct(
        private readonly StartMockInterview $start,
        private readonly AnswerMockInterview $answer,
        private readonly FinishMockInterview $finish,
        private readonly StartVoiceMock $startVoice,
        private readonly EntitlementService $entitlements,
        private readonly GapReport $gaps,
    ) {}

    public function index(Request $request): JsonResponse
    {
        return app(TenantContext::class)->run($request->user()->tenant, function () use ($request): JsonResponse {
            $mocks = MockInterview::query()
                ->where('user_id', $request->user()->id)
                ->orderByDesc('id')
                ->get();

            $best = (int) $mocks->where('status', MockInterview::STATUS_COMPLETED)->max('overall_score');

            return response()->json([
                'data' => [
                    'enabled' => $this->entitlements->settings()->text_practice_enabled,
                    'in_progress_id' => $mocks->firstWhere('status', MockInterview::STATUS_IN_PROGRESS)?->id,
                    'best_score' => $best,
                    'human_mock_unlocked' => $best >= (int) config('mocks.human_gate_score', 70),
                    // Per-module mock quotas the student has unlocked (PRD §6.6).
                    'module_mocks' => ModuleMockRequirement::query()
                        ->where('user_id', $request->user()->id)
                        ->with('module:id,name')
                        ->orderBy('unlocked_at')
                        ->get()
                        ->map(fn (ModuleMockRequirement $r) => [
                            'module' => $r->module?->name,
                            'required' => $r->required,
                            'completed' => $r->completed,
                            'remaining' => $r->remaining(),
                            'cleared' => $r->cleared_at !== null,
                        ])
                        ->values(),
                    // Skill choices the student can practise (Python, SQL, …).
                    'blueprints' => MockBlueprint::availableFor($request->user())
                        ->map(fn ($b) => ['id' => $b->id, 'skill' => $b->skill, 'role_title' => $b->role_title])
                        ->values(),
                    'gap_report' => $this->gaps->for($request->user()),
                    'voice' => [
                        'credits' => $this->entitlements->balance($request->user(), EntitlementFeature::VoiceMock->value),
                        // False until a voice-call provider (Vapi) is configured —
                        // the UI then offers the in-browser interview room instead.
                        'provider_ready' => (string) config('services.vapi.api_key') !== '',
                        'max_minutes' => intdiv((int) config('mocks.voice.max_seconds', 600), 60),
                        'in_progress' => $mocks->first(fn (MockInterview $m) => $m->mode === MockInterview::MODE_VOICE
                            && $m->status === MockInterview::STATUS_IN_PROGRESS)?->only(['id', 'join_url']),
                        'topups' => $this->voiceTopups(),
                    ],
                    'mocks' => $mocks
                        ->where('status', MockInterview::STATUS_COMPLETED)
                        ->values()
                        ->map(fn (MockInterview $m) => [
                            'id' => $m->id,
                            'overall_score' => $m->overall_score,
                            'completed_at' => $m->completed_at?->toIso8601String(),
                        ]),
                ],
            ]);
        });
    }

    public function store(Request $request): JsonResponse
    {
        return app(TenantContext::class)->run($request->user()->tenant, function () use ($request): JsonResponse {
            try {
                $interview = $this->start->handle(
                    $request->user(),
                    $request->filled('blueprint_id') ? (int) $request->integer('blueprint_id') : null,
                    $request->boolean('room'),
                );
            } catch (ValidationException $e) {
                // Empty wallet on a room start: answer with the top-up products
                // so the UI can offer the packs in place (mirrors storeVoice).
                if (str_contains($e->getMessage(), 'credits')) {
                    return response()->json([
                        'error' => [
                            'code' => 'no_voice_credits',
                            'message' => 'You are out of interview room sessions.',
                            'topups' => $this->voiceTopups(),
                        ],
                    ], 402);
                }

                throw $e;
            }

            return $this->session($interview, 201);
        });
    }

    public function show(Request $request, int $mock): JsonResponse
    {
        return app(TenantContext::class)->run($request->user()->tenant, function () use ($request, $mock): JsonResponse {
            return $this->session($this->owned($request, $mock));
        });
    }

    public function answer(Request $request, int $mock): JsonResponse
    {
        $validated = $request->validate(['answer' => ['required', 'string', 'max:5000']]);

        return $this->guardBudget(fn (): JsonResponse => app(TenantContext::class)->run(
            $request->user()->tenant,
            function () use ($request, $mock, $validated): JsonResponse {
                $interview = $this->owned($request, $mock);
                $this->answer->handle($interview, $validated['answer']);

                return $this->session($interview->refresh());
            },
        ));
    }

    /**
     * Proctoring close: the interview room ends the session when the student
     * repeatedly leaves the tab. No refund — the credit was spent on a session
     * that was abandoned by cheating, mirroring a real interview walk-out.
     */
    public function abandon(Request $request, int $mock): JsonResponse
    {
        return app(TenantContext::class)->run($request->user()->tenant, function () use ($request, $mock): JsonResponse {
            $interview = $this->owned($request, $mock);

            if ($interview->status === MockInterview::STATUS_IN_PROGRESS) {
                $interview->update(['status' => MockInterview::STATUS_ABANDONED, 'completed_at' => now()]);
            }

            return $this->session($interview->refresh());
        });
    }

    public function finish(Request $request, int $mock): JsonResponse
    {
        return $this->guardBudget(fn (): JsonResponse => app(TenantContext::class)->run(
            $request->user()->tenant,
            function () use ($request, $mock): JsonResponse {
                $interview = $this->finish->handle($this->owned($request, $mock));

                return $this->session($interview);
            },
        ));
    }

    /**
     * Start (or resume) a voice session. An empty wallet answers 402 with the
     * one-tap top-up products instead of a bare error.
     */
    public function storeVoice(Request $request): JsonResponse
    {
        return app(TenantContext::class)->run($request->user()->tenant, function () use ($request): JsonResponse {
            try {
                $interview = $this->startVoice->handle($request->user());
            } catch (ValidationException $e) {
                if (str_contains($e->getMessage(), 'credits')) {
                    return response()->json([
                        'error' => [
                            'code' => 'no_voice_credits',
                            'message' => 'You are out of voice interview credits.',
                            'topups' => $this->voiceTopups(),
                        ],
                    ], 402);
                }

                throw $e;
            }

            return response()->json(['data' => [
                'id' => $interview->id,
                'join_url' => $interview->join_url,
                'session_id' => $interview->provider_session_id,
                'max_seconds' => (int) config('mocks.voice.max_seconds', 600),
            ]], 201);
        });
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function voiceTopups(): array
    {
        return Product::query()
            ->where('feature', EntitlementFeature::VoiceMock->value)
            ->where('active', true)
            ->orderBy('price_paise')
            ->get(['id', 'sku', 'name', 'price_paise', 'grant_amount'])
            ->map(fn (Product $p) => [
                'product_id' => $p->id,
                'sku' => $p->sku,
                'name' => $p->name,
                'price_paise' => $p->price_paise,
                'sessions' => $p->grant_amount,
            ])
            ->all();
    }

    private function owned(Request $request, int $id): MockInterview
    {
        return MockInterview::query()
            ->where('user_id', $request->user()->id)
            ->findOrFail($id);
    }

    private function session(MockInterview $interview, int $status = 200): JsonResponse
    {
        $interview->load(['turns' => fn ($q) => $q->orderBy('id'), 'blueprint:id,role_title']);
        $max = (int) config('mocks.max_questions', 6);

        return response()->json([
            'data' => [
                'id' => $interview->id,
                'status' => $interview->status,
                'mode' => $interview->mode,
                'join_url' => $interview->status === MockInterview::STATUS_IN_PROGRESS ? $interview->join_url : null,
                'duration_seconds' => $interview->duration_seconds,
                'role_title' => $interview->blueprint?->role_title,
                'questions_asked' => $interview->turns->where('role', 'interviewer')->count(),
                'max_questions' => $max,
                'min_answers' => (int) config('mocks.min_answers', 2),
                'ready_to_finish' => $interview->turns->where('role', 'interviewer')->count() >= $max,
                'overall_score' => $interview->overall_score,
                'scorecard' => $interview->scorecard,
                'scorecard_source' => $interview->scorecard_source,
                'turns' => $interview->turns->map(fn ($t) => [
                    'id' => $t->id,
                    'role' => $t->role,
                    'body' => $t->body,
                ])->values(),
            ],
        ], $status);
    }

    /**
     * @param  callable(): JsonResponse  $callback
     */
    private function guardBudget(callable $callback): JsonResponse
    {
        try {
            return $callback();
        } catch (AiBudgetExceeded) {
            return response()->json([
                'error' => ['code' => 'ai_budget_exceeded', 'message' => 'You have reached today\'s AI practice limit. Please try again tomorrow.'],
            ], 429);
        }
    }
}
