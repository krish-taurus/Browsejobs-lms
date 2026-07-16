<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\Scoring\RecomputeAllScores;
use App\Enums\ActivityType;
use App\Enums\BatchMemberStatus;
use App\Models\ActivityEvent;
use App\Models\BatchMember;
use App\Models\Tenant;
use App\Models\Topic;
use App\Models\TopicCompletion;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Seeder;

/**
 * Makes the Coach Panel + counselor Risk dashboard (P3.2) demo-able immediately:
 * gives one "star" student real learning telemetry (completions + a recent
 * activity streak) so their panel shows a healthy PRI, then rescoring every
 * active student so the Risk board is populated (the disengaged surface as
 * at-risk — exactly who a counselor should call).
 */
class ScoringSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::query()->where('slug', 'browsejobs')->first();
        if ($tenant === null) {
            return;
        }

        $occupying = array_map(fn (BatchMemberStatus $s) => $s->value, BatchMemberStatus::occupying());

        app(TenantContext::class)->run($tenant, function () use ($tenant, $occupying): void {
            $member = BatchMember::query()
                ->whereIn('status', $occupying)
                ->with('batch:id,course_id')
                ->first();

            if ($member?->batch?->course_id !== null) {
                $studentId = $member->user_id;

                // Complete the first two-thirds of the course's topics.
                $topics = Topic::query()
                    ->whereHas('module', fn ($q) => $q->where('course_id', $member->batch->course_id))
                    ->orderBy('module_id')
                    ->orderBy('position')
                    ->pluck('id');

                foreach ($topics->take((int) ceil($topics->count() * 2 / 3)) as $i => $topicId) {
                    TopicCompletion::query()->firstOrCreate(
                        ['tenant_id' => $tenant->id, 'user_id' => $studentId, 'topic_id' => $topicId],
                        ['completed_at' => now()->subDays(10 - min(9, (int) $i))],
                    );
                }

                // A live 6-day login streak.
                for ($d = 0; $d < 6; $d++) {
                    for ($n = 0; $n < 4; $n++) {
                        ActivityEvent::query()->create([
                            'tenant_id' => $tenant->id,
                            'user_id' => $studentId,
                            'type' => ActivityType::Login->value,
                            'occurred_at' => now()->subDays($d)->setTime(9, $n * 5),
                        ]);
                    }
                }
            }
        });

        app(RecomputeAllScores::class)->handle();
    }
}
