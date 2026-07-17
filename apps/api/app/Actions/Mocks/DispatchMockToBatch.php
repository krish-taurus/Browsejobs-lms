<?php

declare(strict_types=1);

namespace App\Actions\Mocks;

use App\Enums\BatchMemberStatus;
use App\Models\Batch;
use App\Models\InAppNotification;
use App\Models\MockBlueprint;
use App\Models\User;
use App\Support\Messaging\Messenger;
use App\Support\Tenancy\TenantContext;

/**
 * Sends a specific skill mock (e.g. the Python or SQL mock) to every active student in a
 * batch (PRD §6.6) — the "send the mock for the class I just took" action. Each student
 * gets an in-app notification linking straight to that blueprint, plus the reused
 * mock_nudge message on WhatsApp/email.
 */
final readonly class DispatchMockToBatch
{
    public function __construct(private Messenger $messenger) {}

    /** @return int students notified */
    public function handle(Batch $batch, MockBlueprint $blueprint, ?User $actor = null): int
    {
        return app(TenantContext::class)->run($batch->tenant, function () use ($batch, $blueprint): int {
            $occupying = array_map(fn (BatchMemberStatus $s) => $s->value, BatchMemberStatus::occupying());
            $label = $blueprint->skill ?? $blueprint->role_title;
            $redirect = rtrim((string) config('app.frontend_url', ''), '/')."/mock?start={$blueprint->id}";

            $count = 0;
            foreach ($batch->members()->whereIn('status', $occupying)->with('student')->get() as $member) {
                $student = $member->student;
                if ($student === null) {
                    continue;
                }

                InAppNotification::query()->create([
                    'tenant_id' => $student->tenant_id,
                    'user_id' => $student->id,
                    'title' => "New practice: {$label} mock",
                    'body' => 'Your trainer sent you a practice interview — 10 minutes locks it in.',
                    'url' => "/mock?start={$blueprint->id}",
                ]);

                $this->messenger->send($student, 'mock_nudge', [
                    'name' => $student->name,
                    'topic' => $label,
                ], [
                    'magic' => ['action' => 'mock.start', 'payload' => ['redirect' => $redirect]],
                ]);

                $count++;
            }

            return $count;
        });
    }
}
