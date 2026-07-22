<?php

declare(strict_types=1);

namespace App\Actions\Reviews;

use App\Enums\BatchMemberStatus;
use App\Enums\ReviewStage;
use App\Models\Batch;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use App\Support\Tenancy\TenantContext;
use Illuminate\Validation\ValidationException;

/**
 * Sends review requests by hand from the admin (Platform Spec §3) — to a whole
 * batch, a set of named students, or both. Defaults to forced (bypasses the
 * per-stage dedup) since a manual send is a deliberate re-ask. Every target is
 * tenant-scoped: a batch or user id from another tenant is silently ignored.
 *
 * @phpstan-type ManualSummary array{sent: int, skipped: int, targeted: int}
 */
final readonly class SendManualReviewRequests
{
    public function __construct(
        private SendReviewRequest $send,
        private AuditLogger $audit,
    ) {}

    /**
     * @param  array{batch_id?: int|null, user_ids?: list<int>, stage?: string|null, force?: bool}  $input
     * @return ManualSummary
     */
    public function handle(User $actor, array $input): array
    {
        $tenant = $actor->tenant;
        $stage = ReviewStage::tryFrom((string) ($input['stage'] ?? '')) ?? ReviewStage::Manual;
        $force = (bool) ($input['force'] ?? true);

        return app(TenantContext::class)->run($tenant, function () use ($actor, $input, $stage, $force): array {
            /** @var array<int, User> $targets keyed by id to de-dupe overlaps */
            $targets = [];
            $batch = null;

            if (! empty($input['batch_id'])) {
                $batch = Batch::query()->find((int) $input['batch_id']); // tenant-scoped
                if ($batch !== null) {
                    $occupying = array_map(fn (BatchMemberStatus $s) => $s->value, BatchMemberStatus::occupying());
                    foreach ($batch->members()->whereIn('status', $occupying)->with('student')->get() as $member) {
                        if ($member->student !== null) {
                            $targets[$member->student->id] = $member->student;
                        }
                    }
                }
            }

            if (! empty($input['user_ids'])) {
                $students = User::query()
                    ->where('user_type', 'student')
                    ->whereIn('id', $input['user_ids'])
                    ->get();
                foreach ($students as $student) {
                    $targets[$student->id] = $student;
                }
            }

            if ($targets === []) {
                throw ValidationException::withMessages(['target' => 'Pick a batch or at least one student to send to.']);
            }

            $sent = 0;
            foreach ($targets as $student) {
                if ($this->send->handle($student, $stage, $batch, force: $force)) {
                    $sent++;
                }
            }

            $targeted = count($targets);

            $this->audit->log(
                action: 'review_request.manual_sent',
                target: $actor,
                metadata: ['stage' => $stage->value, 'force' => $force, 'targeted' => $targeted, 'sent' => $sent, 'batch_id' => $batch?->id],
                actor: $actor,
            );

            return ['sent' => $sent, 'skipped' => $targeted - $sent, 'targeted' => $targeted];
        });
    }
}
