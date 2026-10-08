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
use Illuminate\Support\Facades\Storage;
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
                ->with('blueprint:id,user_id,employer_job_id,job_feed_item_id')
                ->orderByDesc('id')
                ->get();

            $best = (int) $mocks->where('status', MockInterview::STATUS_COMPLETED)->max('overall_score');

            // The room flow's own free-tier cap (separate from the voice_mock
            // wallet above, and from the employer-JD interview cap) — scoped
            // to whichever blueprint a fresh "Start a voice interview" click
            // would actually use, so the portal can show a real count instead
            // of the unrelated (and, without a telephony provider, unused)
            // wallet balance.
            $roomBlueprint = MockBlueprint::activeFor($request->user());
            $roomAttemptsLimit = $this->entitlements->settings()->general_mock_attempts_per_blueprint;
            $roomAttemptsUsed = $roomBlueprint === null ? 0 : MockInterview::query()
                ->where('user_id', $request->user()->id)
                ->where('mock_blueprint_id', $roomBlueprint->id)
                ->where('is_room', true)
                ->count();

            return response()->json([
                'data' => [
                    'enabled' => $this->entitlements->settings()->text_practice_enabled,
                    // Practice only — a job or CV interview left open must not
                    // surface as "resume" on the text-practice card.
                    'in_progress_id' => $mocks->first(fn (MockInterview $m) => $m->status === MockInterview::STATUS_IN_PROGRESS
                        && $m->kind() === MockInterview::KIND_PRACTICE)?->id,
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
                        'max_minutes' => intdiv((int) config('mocks.voice.max_seconds', 600), 60),
                        'in_progress' => $mocks->first(fn (MockInterview $m) => $m->mode === MockInterview::MODE_VOICE
                            && $m->status === MockInterview::STATUS_IN_PROGRESS)?->only(['id', 'join_url']),
                        'topups' => $this->voiceTopups(),
                        // Whether the telephony provider can actually place a
                        // call. Without it the portal offers the in-browser
                        // spoken interview instead of a button that only ever
                        // fails and refunds the credit.
                        'provider_ready' => (string) config('services.vapi.api_key', '') !== '',
                        'room_attempts_used' => $roomAttemptsUsed,
                        'room_attempts_limit' => $roomAttemptsLimit,
                    ],
                    'mocks' => $mocks
                        ->where('status', MockInterview::STATUS_COMPLETED)
                        ->values()
                        ->map(fn (MockInterview $m) => [
                            'id' => $m->id,
                            'kind' => $m->kind(),
                            'overall_score' => $m->overall_score,
                            'completed_at' => $m->completed_at?->toIso8601String(),
                        ]),
                    // How many sessions of each kind the student has — the
                    // counts on the Practice / Voice / Job / CV tabs.
                    'kind_counts' => collect(MockInterview::KINDS)
                        ->mapWithKeys(fn (string $k) => [$k => $mocks->filter(fn (MockInterview $m) => $m->kind() === $k)->count()]),
                ],
            ]);
        });
    }

    /**
     * One kind's sessions for the student — the list behind
     * /student-ai-mock/{practice|voice|job|cv}.
     */
    public function history(Request $request): JsonResponse
    {
        $kind = (string) $request->query('kind', MockInterview::KIND_PRACTICE);
        if (! in_array($kind, MockInterview::KINDS, true)) {
            throw ValidationException::withMessages(['kind' => 'Unknown interview type.']);
        }

        return app(TenantContext::class)->run($request->user()->tenant, function () use ($request, $kind): JsonResponse {
            $mocks = MockInterview::query()
                ->where('user_id', $request->user()->id)
                ->ofKind($kind)
                ->with('blueprint:id,role_title,user_id,employer_job_id,job_feed_item_id')
                ->orderByDesc('id')
                ->limit(200)
                ->get();

            return response()->json([
                'data' => [
                    'kind' => $kind,
                    'mocks' => $mocks->map(fn (MockInterview $m) => [
                        'id' => $m->id,
                        'kind' => $kind,
                        'role_title' => $m->blueprint?->role_title,
                        'status' => $m->status,
                        'is_room' => (bool) $m->is_room,
                        'overall_score' => $m->overall_score,
                        'started_at' => $m->started_at?->toIso8601String(),
                        'completed_at' => $m->completed_at?->toIso8601String(),
                    ])->values(),
                ],
            ]);
        });
    }

    public function store(Request $request): JsonResponse
    {
        return app(TenantContext::class)->run($request->user()->tenant, function () use ($request): JsonResponse {
            $interview = $this->start->handle(
                $request->user(),
                $request->filled('blueprint_id') ? (int) $request->integer('blueprint_id') : null,
                $request->boolean('is_room'),
            );

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
     * abandoned by cheating, mirroring a real interview walk-out.
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
     * The interview room's own webcam+mic capture, uploaded once the call
     * ends — the candidate's evidence to the employer (PRD-E), captured
     * without needing a live-call provider. Silently overwrites a prior
     * upload for the same interview (a retake from the room re-uploads).
     */
    public function uploadRecording(Request $request, int $mock): JsonResponse
    {
        $request->validate([
            'recording' => ['required', 'file', 'max:76800'], // 75MB — ~15 min of webcam+mic at modest bitrate
        ]);

        return app(TenantContext::class)->run($request->user()->tenant, function () use ($request, $mock): JsonResponse {
            $interview = $this->owned($request, $mock);
            $file = $request->file('recording');
            $extension = strtolower($file->getClientOriginalExtension()) ?: 'webm';
            $path = "mock-recordings/{$interview->tenant_id}/{$interview->id}.{$extension}";

            Storage::disk('s3')->put($path, (string) $file->get());
            $interview->update(['recording_url' => $path]);

            return response()->json(['ok' => true]);
        });
    }

    /**
     * A signed, time-limited URL to the candidate's own recording (candidate
     * request, Aug 2026) — same signing pattern EmployerApplicationResource
     * already uses for the employer's view of it, so the raw bucket path
     * never reaches the browser either way. This reverses an earlier,
     * deliberate call ("a recording is the employer's evidence, not
     * something a candidate re-plays for themselves" — see the migration
     * that added recording_url) at the candidate's explicit request.
     */
    public function recording(Request $request, int $mock): JsonResponse
    {
        $interview = $this->owned($request, $mock);

        if ($interview->recording_url === null) {
            return response()->json(['error' => ['message' => 'No recording for this interview.']], 404);
        }

        $url = str_starts_with($interview->recording_url, 'http')
            ? $interview->recording_url
            : Storage::disk('s3')->temporaryUrl($interview->recording_url, now()->addMinutes(30));

        return response()->json(['data' => ['url' => $url]]);
    }

    /**
     * Deletes only the recording file — the interview's score, scorecard,
     * and transcript are untouched, so this is a storage/privacy choice,
     * not a way to erase a result.
     */
    public function deleteRecording(Request $request, int $mock): JsonResponse
    {
        $interview = $this->owned($request, $mock);

        if ($interview->recording_url !== null) {
            if (! str_starts_with($interview->recording_url, 'http')) {
                Storage::disk('s3')->delete($interview->recording_url);
            }
            $interview->update(['recording_url' => null]);
        }

        return response()->json(['ok' => true]);
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
        $interview->load(['turns' => fn ($q) => $q->orderBy('id'), 'blueprint:id,role_title,max_questions,user_id,employer_job_id,job_feed_item_id']);
        // A blueprint may run longer than the platform default (the AI
        // Readiness Interview asks 15) — mirrors AnswerMockInterview's own
        // override so the two never disagree about when the session is done.
        $max = (int) ($interview->blueprint?->max_questions ?? config('mocks.max_questions', 6));
        $questionsAsked = $interview->turns->where('role', 'interviewer')->count();
        // Asked, not answered, is not the same as done: the candidate must
        // actually have answered every question asked, the final one
        // included, or "ready to finish" hides the Answer control on it —
        // asking the last question and being told there's nothing left to
        // do in the same breath, with no way to ever answer it.
        $answered = $interview->turns->where('role', 'candidate')->count();

        return response()->json([
            'data' => [
                'id' => $interview->id,
                // practice | voice | job | cv — the portal keeps each kind
                // under its own URL (/student-ai-mock/{kind}/{id}).
                'kind' => $interview->kind(),
                'status' => $interview->status,
                'mode' => $interview->mode,
                // The plain (non-room) page uses this to redirect a room-kind
                // interview back to /mock/{id}/room instead of rendering its
                // own text UI — a candidate landing here (browser back, an
                // old bookmark, the generic Mock Interviews list) should
                // never be quietly dropped into the wrong interview format.
                'is_room' => (bool) $interview->is_room,
                'join_url' => $interview->status === MockInterview::STATUS_IN_PROGRESS ? $interview->join_url : null,
                'duration_seconds' => $interview->duration_seconds,
                'role_title' => $interview->blueprint?->role_title,
                'questions_asked' => $questionsAsked,
                'max_questions' => $max,
                'min_answers' => (int) config('mocks.min_answers', 2),
                'ready_to_finish' => $questionsAsked >= $max && $answered >= $questionsAsked,
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
