<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tutor;

use App\Actions\Tutor\AskTutor;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tutor\AskTutorRequest;
use App\Http\Resources\TutorConversationResource;
use App\Models\CodingLab;
use App\Models\TutorConversation;
use App\Services\AI\AiBudgetExceeded;
use App\Support\Tenancy\TenantContext;
use App\Support\Tutor\CitationResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The student AI Tutor (PRD §6.4). Under auth:sanctum without tenant.user, so every
 * action wraps in the student's tenant context. Answers are computed synchronously
 * (interactive chat); the gateway enforces the daily token budget.
 */
final class TutorController extends Controller
{
    public function __construct(
        private readonly AskTutor $ask,
        private readonly CitationResolver $citations,
    ) {}

    public function index(Request $request): JsonResponse
    {
        return app(TenantContext::class)->run($request->user()->tenant, function () use ($request): JsonResponse {
            $conversations = TutorConversation::query()
                ->where('student_id', $request->user()->id)
                ->withCount('messages')
                ->orderByDesc('last_message_at')
                ->orderByDesc('id')
                ->get();

            return TutorConversationResource::collection($conversations)->response();
        });
    }

    public function show(Request $request, int $conversation): JsonResponse
    {
        return app(TenantContext::class)->run($request->user()->tenant, function () use ($request, $conversation): JsonResponse {
            $model = $this->ownedConversation($request, $conversation);

            return $this->thread($model);
        });
    }

    public function store(AskTutorRequest $request): JsonResponse
    {
        return $this->guardBudget(fn (): JsonResponse => app(TenantContext::class)->run($request->user()->tenant, function () use ($request): JsonResponse {
            $student = $request->user();

            $conversation = $request->filled('conversation_id')
                ? $this->ownedConversation($request, (int) $request->integer('conversation_id'))
                : TutorConversation::query()->create([
                    'tenant_id' => $student->tenant_id,
                    'student_id' => $student->id,
                    'title' => str($request->string('question'))->limit(60)->toString(),
                ]);

            $this->ask->handle($student, $request->string('question')->toString(), $conversation);

            return $this->thread($conversation->fresh());
        }));
    }

    public function askLab(AskTutorRequest $request, int $lesson): JsonResponse
    {
        return $this->guardBudget(fn (): JsonResponse => app(TenantContext::class)->run($request->user()->tenant, function () use ($request, $lesson): JsonResponse {
            $student = $request->user();
            $lab = CodingLab::query()->with('lesson:id,title')->where('lesson_id', $lesson)->firstOrFail();

            $conversation = $request->filled('conversation_id')
                ? $this->ownedConversation($request, (int) $request->integer('conversation_id'))
                : TutorConversation::query()->firstOrCreate(
                    ['student_id' => $student->id, 'lesson_id' => $lesson],
                    ['tenant_id' => $student->tenant_id, 'title' => 'Lab: '.($lab->lesson?->title ?? 'Coding lab')],
                );

            $this->ask->handle($student, $request->string('question')->toString(), $conversation, $lab);

            return $this->thread($conversation->fresh());
        }));
    }

    private function ownedConversation(Request $request, int $id): TutorConversation
    {
        return TutorConversation::query()
            ->where('student_id', $request->user()->id)
            ->findOrFail($id);
    }

    private function thread(TutorConversation $conversation): JsonResponse
    {
        $conversation->load(['messages' => fn ($q) => $q->orderBy('id')]);
        $this->citations->attach($conversation->messages);

        return (new TutorConversationResource($conversation))->response();
    }

    /**
     * @param  callable(): JsonResponse  $callback
     */
    private function guardBudget(callable $callback): JsonResponse
    {
        try {
            return $callback();
        } catch (AiBudgetExceeded $e) {
            return response()->json([
                'error' => ['code' => 'ai_budget_exceeded', 'message' => 'You have reached today\'s AI tutor limit. Please try again tomorrow.'],
            ], 429);
        }
    }
}
