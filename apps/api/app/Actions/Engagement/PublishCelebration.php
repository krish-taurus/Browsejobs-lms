<?php

declare(strict_types=1);

namespace App\Actions\Engagement;

use App\Models\Celebration;
use App\Models\InAppNotification;
use App\Support\Engagement\ActiveStudents;

/**
 * Publishes a consented celebration to the wall and broadcasts it in-app to
 * the tenant's active students (PRD §6.18). In-app only — promotional
 * WhatsApp stays behind the weekly opt-in digest.
 */
final readonly class PublishCelebration
{
    public function handle(Celebration $celebration): Celebration
    {
        if ($celebration->published_at !== null) {
            return $celebration; // Idempotent: never re-broadcast.
        }

        $celebration->update(['published_at' => now()]);

        $title = sprintf(
            '🎉 %s just received an offer as %s!',
            $celebration->displayName(),
            $celebration->role_title,
        );

        ActiveStudents::idsFor((int) $celebration->tenant_id, (int) config('engagement.celebration_fanout', 200))
            ->reject(fn (int $id) => $id === $celebration->student_id)
            ->each(fn (int $userId) => InAppNotification::query()->create([
                'tenant_id' => $celebration->tenant_id,
                'user_id' => $userId,
                'title' => $title,
                'body' => 'See your personalised path to the same on the Pulse page.',
                'url' => '/pulse',
            ]));

        return $celebration->refresh();
    }
}
