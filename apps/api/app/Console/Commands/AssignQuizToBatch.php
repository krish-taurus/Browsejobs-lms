<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\BatchMemberStatus;
use App\Jobs\CheckQuizCompletion;
use App\Models\Batch;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Messaging\Messenger;
use App\Support\Tenancy\TenantContext;
use Illuminate\Console\Command;
use Throwable;

/**
 * Give a quiz to every student in a batch.
 *
 * Until now a quiz only reached a student when they completed the module it
 * hangs off ({@see \App\Listeners\DispatchModuleQuiz}), which is no use when a
 * trainer wants to set a test for a running batch. This assigns it directly:
 * one attempt per seated student, each with a deadline, a WhatsApp/email link
 * to sit it, and the usual reminder and flag jobs.
 *
 * Idempotent — a student who already has an attempt is skipped, so re-running
 * after adding students to the batch only reaches the new ones. Pass --remind
 * to also chase everyone who has the quiz but has not finished it.
 */
final class AssignQuizToBatch extends Command
{
    protected $signature = 'quiz:assign
        {quiz : quiz id}
        {batch : batch id or number}
        {--due-days=3 : days the students have to finish}
        {--remind : also re-send the link to students who already have it and have not finished}
        {--tenant=1}';

    protected $description = 'Assign a quiz to every student in a batch';

    public function handle(Messenger $messenger): int
    {
        $tenant = Tenant::query()->find((int) $this->option('tenant'));

        if ($tenant === null) {
            $this->error('Tenant not found.');

            return self::FAILURE;
        }

        return app(TenantContext::class)->run($tenant, function () use ($messenger): int {
            $quiz = Quiz::query()->find((int) $this->argument('quiz'));

            if ($quiz === null) {
                $this->error('Quiz not found.');

                return self::FAILURE;
            }

            if (! $quiz->isDispatchable()) {
                $this->error('This quiz is not approved yet, or has no questions.');

                return self::FAILURE;
            }

            $reference = (string) $this->argument('batch');

            $batch = Batch::query()
                ->when(
                    is_numeric($reference),
                    fn ($q) => $q->whereKey((int) $reference),
                    fn ($q) => $q->where('number', $reference),
                )
                ->first();

            if ($batch === null) {
                $this->error("Batch not found: {$reference}");

                return self::FAILURE;
            }

            $occupying = array_map(fn (BatchMemberStatus $s) => $s->value, BatchMemberStatus::occupying());
            $members = $batch->members()->whereIn('status', $occupying)->with('student')->get();

            if ($members->isEmpty()) {
                $this->error('That batch has no seated students.');

                return self::FAILURE;
            }

            // The due date lives in its own column: `deadline_at` is the sitting
            // timer and gets overwritten the moment the student opens the quiz.
            $due = now()->addDays(max(1, (int) $this->option('due-days')))->endOfDay();
            $remind = (bool) $this->option('remind');

            $assigned = 0;
            $reminded = 0;
            $skipped = 0;

            foreach ($members as $member) {
                $student = $member->student;

                if ($student === null) {
                    continue;
                }

                $attempt = QuizAttempt::query()->firstOrCreate(
                    ['quiz_id' => $quiz->id, 'user_id' => $student->id],
                    ['tenant_id' => $student->tenant_id, 'status' => 'pending', 'due_at' => $due],
                );

                if (! $attempt->wasRecentlyCreated) {
                    // Already sat it — nothing to chase.
                    if (! $remind || ! in_array($attempt->status->value, ['pending', 'in_progress'], true)) {
                        $skipped++;

                        continue;
                    }

                    $attempt->update(['due_at' => $due]);
                    $this->notify($messenger, $student, $quiz, $attempt);
                    $reminded++;

                    continue;
                }

                $this->notify($messenger, $student, $quiz, $attempt);

                CheckQuizCompletion::dispatch($attempt->id, 'reminder')->delay(now()->addHours(48));
                CheckQuizCompletion::dispatch($attempt->id, 'flag')->delay(now()->addHours(96));

                $assigned++;
            }

            $this->line((string) json_encode([
                'quiz' => $quiz->title,
                'batch' => $batch->number,
                'assigned' => $assigned,
                'reminded' => $reminded,
                'already_had_it' => $skipped,
                'due' => $due->toDateString(),
            ], JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        });
    }

    /**
     * WhatsApp + email the student a magic link straight into the sitting. The
     * quiz also shows on their Quizzes page, so a messaging failure is a missed
     * nudge, never a lost test — it must not cost them the attempt.
     */
    private function notify(Messenger $messenger, User $student, Quiz $quiz, QuizAttempt $attempt): void
    {
        $redirect = rtrim((string) config('app.frontend_url', ''), '/')."/mcq/{$attempt->id}";

        try {
            $messenger->send($student, 'mcq_dispatch', [
                'name' => $student->name,
                'module' => $quiz->title,
            ], [
                'magic' => [
                    'action' => 'mcq.attempt',
                    'payload' => ['attempt_id' => $attempt->id, 'redirect' => $redirect],
                ],
            ]);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
