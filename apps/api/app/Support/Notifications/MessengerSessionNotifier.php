<?php

declare(strict_types=1);

namespace App\Support\Notifications;

use App\Enums\MessageChannel;
use App\Models\LiveSession;
use App\Models\MessageTemplate;
use App\Models\Scopes\TenantScope;
use App\Models\User;
use App\Support\Messaging\Messenger;
use Carbon\CarbonInterface;

/**
 * Routes live-class notifications through the messaging hub (P2.4). The magic
 * join link is pre-built by the caller and passed straight into the template.
 */
final class MessengerSessionNotifier implements SessionNotifier
{
    public function __construct(private readonly Messenger $messenger) {}

    public function reminder(User $student, LiveSession $session, string $magicJoinUrl, string $window): void
    {
        $key = $this->reminderKey($window, $session->tenant_id);

        // The 5-minute message shows a plain, recognisable address rather than a
        // long signed magic URL. Every other rung keeps the one-tap link.
        $joinUrl = $key === 'class_reminder_5min'
            ? ((string) config('live_classes.join_url') ?: $magicJoinUrl)
            : $magicJoinUrl;

        $this->messenger->send($student, $key, [
            'name' => $student->name,
            'title' => $session->title,
            'batch' => $this->batchLabel($session),
            'window' => $window,
            'time' => $session->scheduled_start->format('g:i A'),
            'link' => $joinUrl,
        ]);
    }

    /**
     * The last rung ("get ready, we start in 5 minutes") reads differently from
     * the 12h/2h/1h ones, so it has its own copy. It only takes over once Meta
     * has approved its template — until then the generic reminder keeps going,
     * because that one IS approved and therefore actually reaches students.
     */
    private function reminderKey(string $window, ?int $tenantId): string
    {
        if ($window !== '5min') {
            return 'class_reminder';
        }

        $activated = MessageTemplate::query()->withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $tenantId)
            ->where('key', 'class_reminder_5min')
            ->where('channel', MessageChannel::WhatsApp->value)
            ->whereNotNull('name')
            ->exists();

        return $activated ? 'class_reminder_5min' : 'class_reminder';
    }

    public function cancelled(User $student, LiveSession $session, string $reason): void
    {
        $this->messenger->send($student, 'class_cancelled', [
            'name' => $student->name,
            'title' => $session->title,
            'reason' => $reason,
        ]);
    }

    public function rescheduled(User $student, LiveSession $session, CarbonInterface $previousStart, string $reason): void
    {
        $this->messenger->send($student, 'class_rescheduled', [
            'name' => $student->name,
            'title' => $session->title,
            'batch' => $this->batchLabel($session),
            'date' => $session->scheduled_start->format('j F Y'),
            'time' => $session->scheduled_start->format('g:i A'),
            'was' => $previousStart->format('j M, g:i A'),
            'starts' => $session->scheduled_start->format('j M, g:i A'),
            'reason' => $reason,
        ]);
    }

    public function trainerCancelled(User $trainer, LiveSession $session, string $reason): void
    {
        $this->messenger->send($trainer, 'class_cancelled_trainer', [
            'name' => $trainer->name,
            'title' => $session->title,
            'batch' => $this->batchLabel($session),
            'reason' => $reason,
        ]);
    }

    public function trainerRescheduled(User $trainer, LiveSession $session, CarbonInterface $previousStart, string $reason): void
    {
        $this->messenger->send($trainer, 'class_rescheduled_trainer', [
            'name' => $trainer->name,
            'title' => $session->title,
            'batch' => $this->batchLabel($session),
            'starts' => $session->scheduled_start->format('j M, g:i A'),
            'reason' => $reason,
        ]);
    }

    private function batchLabel(LiveSession $session): string
    {
        $batch = $session->batch;
        $course = $batch?->course?->name;

        return $course !== null ? "{$course} | Batch {$batch->number}" : 'your batch';
    }
}
