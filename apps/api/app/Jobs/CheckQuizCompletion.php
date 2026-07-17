<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Crm\CreateTask;
use App\Models\CrmTask;
use App\Models\Lead;
use App\Models\QuizAttempt;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Crm\PhoneNormalizer;
use App\Support\Messaging\Messenger;
use App\Support\Tenancy\TenantContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * The non-completion ladder for a dispatched quiz (PRD §6.5): `reminder` (48h → nudge
 * the student again) and `flag` (96h → counselor follow-up task). Armed with a delay
 * at dispatch; each phase is idempotent and self-guards (sync-driver safe) via the
 * attempt's `reminded_at`/`flagged_at`. A submitted/expired attempt short-circuits.
 */
final class CheckQuizCompletion implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly int $attemptId,
        public readonly string $phase,
    ) {}

    public function handle(Messenger $messenger, CreateTask $createTask): void
    {
        $attempt = QuizAttempt::query()->withoutGlobalScopes()->with('quiz.lesson.topic.module')->find($this->attemptId);
        if ($attempt === null || $attempt->status->isTerminal()) {
            return;
        }

        $tenant = Tenant::query()->find($attempt->tenant_id);
        $student = User::query()->withoutGlobalScopes()->find($attempt->user_id);
        if ($tenant === null || $student === null) {
            return;
        }

        app(TenantContext::class)->run($tenant, function () use ($attempt, $student, $messenger, $createTask): void {
            match ($this->phase) {
                'reminder' => $this->remind($attempt, $student, $messenger),
                'flag' => $this->flag($attempt, $student, $createTask),
                default => null,
            };
        });
    }

    private function remind(QuizAttempt $attempt, User $student, Messenger $messenger): void
    {
        if ($attempt->reminded_at !== null || $attempt->created_at->gt(now()->subHours(48))) {
            return;
        }

        $attempt->update(['reminded_at' => now()]);

        $module = $attempt->quiz?->lesson?->topic?->module;
        $redirect = rtrim((string) config('app.frontend_url', ''), '/')."/mcq/{$attempt->id}";

        $messenger->send($student, 'mcq_reminder', [
            'name' => $student->name,
            'module' => $module?->name ?? 'your module',
        ], [
            'magic' => [
                'action' => 'mcq.attempt',
                'payload' => ['attempt_id' => $attempt->id, 'redirect' => $redirect],
            ],
        ]);
    }

    private function flag(QuizAttempt $attempt, User $student, CreateTask $createTask): void
    {
        if ($attempt->flagged_at !== null || $attempt->created_at->gt(now()->subHours(96))) {
            return;
        }

        $attempt->update(['flagged_at' => now()]);

        $module = $attempt->quiz?->lesson?->topic?->module;
        $lead = $this->correlateLead($student);
        if ($lead === null || $lead->assignee === null) {
            return; // no CRM owner to task — the flag timestamp still records the miss
        }

        $createTask->handle(
            $lead,
            $lead->assignee,
            "Follow up: {$student->name} hasn't attempted the {$module?->name} quiz",
            now(),
            null,
            CrmTask::SOURCE_QUIZ,
        );
    }

    private function correlateLead(User $student): ?Lead
    {
        $phone = $student->phone !== null ? PhoneNormalizer::normalize($student->phone) : null;
        $hasPhone = $phone !== null && $phone !== '';
        $hasEmail = $student->email !== null && $student->email !== '';
        if (! $hasPhone && ! $hasEmail) {
            return null;
        }

        return Lead::query()
            ->where('tenant_id', $student->tenant_id)
            ->whereNull('merged_into_id')
            ->where(function ($q) use ($phone, $hasPhone, $hasEmail, $student): void {
                if ($hasPhone) {
                    $q->where('phone_normalized', $phone);
                }
                if ($hasEmail) {
                    $q->orWhere('email', $student->email);
                }
            })
            ->orderBy('id')
            ->first();
    }
}
