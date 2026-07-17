<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\TopicCompleted;
use App\Models\InAppNotification;
use App\Support\Entitlements\EntitlementService;
use App\Support\Messaging\Messenger;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * On TopicCompleted for a mock-enabled topic, nudge the student into a text
 * mock while the material is fresh (PRD §6.6). Fires at most once per
 * user+topic because the event itself fires once per first completion.
 * Silent when the tenant hasn't switched on text practice.
 */
final class DispatchMockNudge implements ShouldQueue
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

        if ($tenant === null || ! $topic->mock_enabled) {
            return;
        }

        app(TenantContext::class)->run($tenant, function () use ($student, $topic): void {
            if (! $this->entitlements->settings()->text_practice_enabled) {
                return;
            }

            $redirect = rtrim((string) config('app.frontend_url', ''), '/').'/mock';

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
                'title' => "Nice — {$topic->name} done. Ready to be interviewed on it?",
                'body' => 'A 10-minute practice interview locks it in while it\'s fresh.',
                'url' => '/mock',
            ]);
        });
    }
}
