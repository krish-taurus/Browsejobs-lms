<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\BatchMemberStatus;
use App\Jobs\CheckQuizCompletion;
use App\Models\Batch;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\Tenant;
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
 * never duplicates or re-notifies.
 */
final class AssignQuizToBatch extends Command
{
    protected $signature = 'quiz:assign
        {quiz : quiz id}
        {batch : batch id or number}
        {--due-days=3 : days the students have to finish}
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

            $deadline = now()->addDays(max(1, (int) $this->option('due-days')));

            $assigned = 0;
            $skipped = 0;

            foreach ($members as $member) {
                $student = $member->student;

                if ($student === null) {
                    continue;
                }

                $attempt = QuizAttempt::query()->firstOrCreate(
                    ['quiz_id' => $quiz->id, 'user_id' => $student->id],
                    ['tenant_id' => $student->tenant_id, 'status' => 'pending', 'deadline_at' => $deadline],
                );

                if (! $attempt->wasRecentlyCreated) {
                    $skipped++;

                    continue;
                }

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
                    // A messaging failure must not cost the student their attempt.
                    report($e);
                }

                CheckQuizCompletion::dispatch($attempt->id, 'reminder')->delay(now()->addHours(48));
                CheckQuizCompletion::dispatch($attempt->id, 'flag')->delay(now()->addHours(96));

                $assigned++;
            }

            $this->line((string) json_encode([
                'quiz' => $quiz->title,
                'batch' => $batch->number,
                'assigned' => $assigned,
                'already_had_it' => $skipped,
                'due' => $deadline->toDateString(),
            ], JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        });
    }
}
