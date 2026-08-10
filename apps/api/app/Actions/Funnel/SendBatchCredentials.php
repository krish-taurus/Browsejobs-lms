<?php

declare(strict_types=1);

namespace App\Actions\Funnel;

use App\Enums\MessageChannel;
use App\Models\Batch;
use App\Models\User;
use App\Support\Messaging\Messenger;

/**
 * Batch onboarding message (WhatsApp + email): the student's batch number and
 * how to sign in — with their registered number/email via a one-time code
 * (OTP). No passwords are ever sent; the LMS is passwordless by default.
 */
final readonly class SendBatchCredentials
{
    public function __construct(private Messenger $messenger) {}

    public function handle(User $student, Batch $batch): void
    {
        $batch->loadMissing('course');
        $course = $batch->course?->name ?? 'your course';

        $vars = [
            'name' => $student->name,
            'batch' => $batch->number,
            'course' => $course,
            // For the Meta template's batch slot: number + course in one value,
            // so the approved template stays course-dynamic without a re-review.
            'batch_course' => "{$batch->number} ({$course})",
            'starts' => $batch->starts_on ? $batch->starts_on->format('D, d M Y') : 'soon',
            'login' => filled($student->email) ? (string) $student->email : (string) $student->phone,
            'link' => rtrim((string) config('app.frontend_url', ''), '/').'/student',
        ];

        foreach ([MessageChannel::WhatsApp, MessageChannel::Email] as $channel) {
            $this->messenger->send($student, 'batch_credentials', $vars, ['channel' => $channel]);
        }
    }
}
