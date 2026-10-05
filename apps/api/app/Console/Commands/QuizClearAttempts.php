<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\Scopes\TenantScope;
use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use Illuminate\Console\Command;

/**
 * Take a quiz back off the people holding it.
 *
 * An assignment is not recorded anywhere of its own: `quiz:assign` writes one
 * attempt row per student, and that row IS the assignment. So a quiz handed to
 * a batch that has since been deleted still shows as held by a dozen people who
 * are now in no batch, and re-assigning it to a fresh batch skips them as
 * "already has an attempt".
 *
 * Clearing the unfinished attempts puts the quiz back to unassigned, ready to
 * be given to a new batch. Finished ones are kept unless --including-submitted
 * is passed, because a submitted attempt is a result somebody earned.
 */
final class QuizClearAttempts extends Command
{
    protected $signature = 'quiz:clear-attempts
        {quiz : quiz id}
        {--including-submitted : also delete finished attempts and their scores}
        {--tenant=1}';

    protected $description = 'Remove attempts so a quiz can be assigned again (keeps submitted results by default)';

    public function handle(): int
    {
        $tenant = Tenant::query()->find((int) $this->option('tenant'));

        if ($tenant === null) {
            $this->error('Tenant not found.');

            return self::FAILURE;
        }

        return app(TenantContext::class)->run($tenant, function (): int {
            $quizId = (int) $this->argument('quiz');
            $quiz = Quiz::query()->withoutGlobalScope(TenantScope::class)->find($quizId);

            if ($quiz === null) {
                $this->error('Quiz not found.');

                return self::FAILURE;
            }

            $attempts = QuizAttempt::query()->withoutGlobalScope(TenantScope::class)
                ->where('quiz_id', $quizId);

            $kept = 0;

            if (! $this->option('including-submitted')) {
                // A submitted attempt is a result someone earned; it is not
                // stale assignment plumbing, so it stays put.
                $kept = (clone $attempts)->where('status', 'submitted')->count();
                $attempts->where('status', '!=', 'submitted');
            }

            $removed = $attempts->delete();

            $this->info(sprintf(
                'Cleared %d attempt(s) from "%s"%s. The quiz can be assigned to a batch again.',
                $removed,
                $quiz->title,
                $kept > 0 ? sprintf(' — %d submitted result(s) kept', $kept) : '',
            ));

            return self::SUCCESS;
        });
    }
}
