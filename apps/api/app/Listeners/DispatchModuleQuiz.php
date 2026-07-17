<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Enums\LessonType;
use App\Events\ModuleCompleted;
use App\Jobs\CheckQuizCompletion;
use App\Models\Lesson;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Support\Messaging\Messenger;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * On ModuleCompleted, dispatch the module's approved quiz to the student (PRD §6.5):
 * open one attempt, WhatsApp/email a magic link into the timed MCQ page, and arm the
 * 48h reminder / 96h counselor-flag ladder. Idempotent — one attempt per student per
 * quiz (the unique index + firstOrCreate prevent double dispatch). Queued.
 */
final class DispatchModuleQuiz implements ShouldQueue
{
    public function __construct(private readonly Messenger $messenger) {}

    public function handle(ModuleCompleted $event): void
    {
        $student = $event->user;
        $module = $event->module;
        $tenant = $student->tenant;
        if ($tenant === null) {
            return;
        }

        app(TenantContext::class)->run($tenant, function () use ($student, $module): void {
            $quizLesson = Lesson::query()
                ->where('type', LessonType::Quiz->value)
                ->whereHas('topic', fn ($q) => $q->where('module_id', $module->id))
                ->orderBy('position')
                ->first();

            if ($quizLesson === null) {
                return;
            }

            $quiz = Quiz::query()->where('lesson_id', $quizLesson->id)->first();
            if ($quiz === null || ! $quiz->isDispatchable()) {
                return;
            }

            $attempt = QuizAttempt::query()->firstOrCreate(
                ['quiz_id' => $quiz->id, 'user_id' => $student->id],
                ['tenant_id' => $student->tenant_id, 'status' => 'pending'],
            );

            if (! $attempt->wasRecentlyCreated) {
                return; // already dispatched — no duplicate message or armed jobs
            }

            $redirect = rtrim((string) config('app.frontend_url', ''), '/')."/mcq/{$attempt->id}";

            $this->messenger->send($student, 'mcq_dispatch', [
                'name' => $student->name,
                'module' => $module->name,
            ], [
                'magic' => [
                    'action' => 'mcq.attempt',
                    'payload' => ['attempt_id' => $attempt->id, 'redirect' => $redirect],
                ],
            ]);

            CheckQuizCompletion::dispatch($attempt->id, 'reminder')->delay(now()->addHours(48));
            CheckQuizCompletion::dispatch($attempt->id, 'flag')->delay(now()->addHours(96));
        });
    }
}
