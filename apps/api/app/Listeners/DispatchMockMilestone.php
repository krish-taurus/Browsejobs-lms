<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Enums\LessonType;
use App\Events\TopicCompleted;
use App\Models\InAppNotification;
use App\Models\MockBlueprint;
use App\Support\Entitlements\EntitlementService;
use App\Support\Messaging\Messenger;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Mock milestone (PRD §6.6): a topic that contains a `mock_milestone` lesson is
 * a qualifying gate on the way to the real interview. When the student
 * completes that topic, auto-send them the AI mock — a message plus an in-app
 * link deep-linked to the course's active blueprint when there is one. Fires
 * once per student+topic (TopicCompleted fires once on first completion) and is
 * silent when the tenant hasn't switched on text practice.
 *
 * This is distinct from the optional per-topic "mock nudge" ({@see
 * DispatchMockNudge}, gated on `mock_enabled`): the milestone is a structural
 * step the syllabus places deliberately.
 */
final class DispatchMockMilestone implements ShouldQueue
{
    public function __construct(
        private readonly Messenger $messenger,
        private readonly EntitlementService $entitlements,
    ) {}

    public function handle(TopicCompleted $event): void
    {
        $student = $event->user;
        $topic = $event->topic;
        $tenant = $student->tenant;

        if ($tenant === null) {
            return;
        }

        $hasMilestone = $topic->lessons()
            ->where('type', LessonType::MockMilestone->value)
            ->exists();

        if (! $hasMilestone) {
            return;
        }

        app(TenantContext::class)->run($tenant, function () use ($student, $topic): void {
            if (! $this->entitlements->settings()->text_practice_enabled) {
                return;
            }

            $blueprint = MockBlueprint::activeFor($student);
            $base = rtrim((string) config('app.frontend_url', ''), '/');
            $redirect = $blueprint !== null ? "{$base}/mock?start={$blueprint->id}" : "{$base}/mock";

            $this->messenger->send($student, 'mock_nudge', [
                'name' => $student->name,
                'topic' => $topic->name,
            ], [
                'magic' => [
                    'action' => 'mock.start',
                    'payload' => ['redirect' => $redirect],
                ],
            ]);

            InAppNotification::query()->create([
                'tenant_id' => $student->tenant_id,
                'user_id' => $student->id,
                'title' => "Milestone reached — {$topic->name}. Time for your qualifying mock.",
                'body' => 'Clear this AI practice interview to move toward the real one. 10 minutes.',
                'url' => '/mock',
            ]);
        });
    }
}
