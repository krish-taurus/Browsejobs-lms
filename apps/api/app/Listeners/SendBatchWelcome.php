<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Enums\MessageChannel;
use App\Events\PaymentCaptured;
use App\Models\Batch;
use App\Models\FeePlan;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Messaging\Messenger;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * On the FIRST captured instalment (the moment a student officially joins),
 * welcomes them into their paid batch on WhatsApp + email and pings the batch's
 * trainer in-app about the new enrollee — so payment auto-onboards the student
 * and keeps the trainer in the loop with no manual step. Queued; fires only on
 * `seq === 1` so later instalments don't re-welcome. Idempotent enough: the
 * webhook only dispatches PaymentCaptured once per instalment (settlement is
 * guarded upstream).
 */
final class SendBatchWelcome implements ShouldQueue
{
    public function __construct(private readonly Messenger $messenger) {}

    public function handle(PaymentCaptured $event): void
    {
        if ($event->instalment->seq !== 1) {
            return; // enrollment happens on the first payment only
        }

        $feePlan = FeePlan::query()->withoutGlobalScopes()
            ->with(['student', 'batch.course', 'batch.trainer'])
            ->find($event->instalment->fee_plan_id);

        $tenant = $feePlan !== null ? Tenant::query()->find($feePlan->tenant_id) : null;
        $student = $feePlan?->student;
        $batch = $feePlan?->batch;
        if ($feePlan === null || $tenant === null || $student === null || $batch === null) {
            return;
        }

        app(TenantContext::class)->run($tenant, function () use ($student, $batch): void {
            $label = $this->batchLabel($batch);
            $vars = [
                'name' => $student->name,
                'batch' => $label,
                'starts' => $batch->starts_on?->format('j M Y') ?? 'soon',
                'link' => rtrim((string) config('app.frontend_url', ''), '/').'/dashboard',
            ];

            // Student: welcome on both channels (important, transactional).
            $this->messenger->send($student, 'batch_welcome', $vars, ['channel' => MessageChannel::WhatsApp]);
            $this->messenger->send($student, 'batch_welcome', $vars, ['channel' => MessageChannel::Email]);

            // Trainer: keep them in the loop about who just joined.
            $trainer = $batch->trainer;
            if ($trainer instanceof User) {
                $this->messenger->send(
                    $trainer,
                    'batch_new_enrollee',
                    ['name' => $trainer->name, 'student' => $student->name, 'batch' => $label],
                    ['channel' => MessageChannel::InApp],
                );
            }
        });
    }

    private function batchLabel(Batch $batch): string
    {
        $course = $batch->course?->name;

        return $course !== null ? "{$course} · Batch {$batch->number}" : "Batch {$batch->number}";
    }
}
