<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\BatchMemberStatus;
use App\Models\BatchMember;
use App\Models\InAppNotification;
use App\Models\NpsPulse;
use App\Models\OnboardingChecklist;
use App\Models\Tenant;
use App\Models\Topic;
use App\Models\TopicCompletion;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

/**
 * Daily care sweep (PRD §6.20): opens NPS pulses at journey milestones
 * (week-1 / mid-course / pre-placement) and creates the week-1 white-glove
 * onboarding checklist (+ counselor welcome-call nudge) for fresh
 * enrolments. Idempotent — unique keys make re-runs safe.
 */
final class RunCareDispatch extends Command
{
    protected $signature = 'care:dispatch';

    protected $description = 'Open milestone NPS pulses and week-1 onboarding checklists';

    public function handle(): int
    {
        Tenant::query()->each(function (Tenant $tenant): void {
            app(TenantContext::class)->run($tenant, function () use ($tenant): void {
                $members = BatchMember::query()
                    ->where('status', BatchMemberStatus::Enrolled->value)
                    ->with(['student:id,tenant_id,name,user_type', 'batch:id,course_id'])
                    ->get()
                    ->filter(fn (BatchMember $m) => $m->student?->user_type === 'student');

                foreach ($members as $member) {
                    $this->onboarding($tenant, $member);
                    $this->pulses($tenant, $member);
                }
            });
        });

        $this->info('Care dispatch complete.');

        return self::SUCCESS;
    }

    private function onboarding(Tenant $tenant, BatchMember $member): void
    {
        $student = $member->student;
        if ($student === null || $member->created_at?->lt(now()->subDays(14))) {
            return; // Only fresh enrolments get the white-glove flow.
        }

        $checklist = OnboardingChecklist::query()->firstOrCreate(
            ['user_id' => $student->id],
            ['tenant_id' => $tenant->id],
        );

        if ($checklist->wasRecentlyCreated) {
            foreach ($this->counselors($tenant) as $counselor) {
                InAppNotification::query()->create([
                    'tenant_id' => $tenant->id,
                    'user_id' => $counselor->id,
                    'title' => "Welcome call due: {$student->name}",
                    'body' => 'New enrolment — schedule the white-glove welcome call and platform tour this week.',
                    'url' => '/admin/care',
                ]);
            }
        }
    }

    private function pulses(Tenant $tenant, BatchMember $member): void
    {
        $student = $member->student;
        if ($student === null) {
            return;
        }

        $milestone = null;

        $progress = $this->progressPct($member);
        if ($progress >= (int) config('retention.nps.pre_placement_progress_pct', 80)) {
            $milestone = 'pre_placement';
        } elseif ($progress >= (int) config('retention.nps.mid_progress_pct', 50)) {
            $milestone = 'mid';
        } elseif ($member->created_at?->lte(now()->subDays((int) config('retention.nps.week1_after_days', 7)))) {
            $milestone = 'week1';
        }

        if ($milestone === null) {
            return;
        }

        $pulse = NpsPulse::query()->firstOrCreate(
            ['user_id' => $student->id, 'milestone' => $milestone],
            ['tenant_id' => $tenant->id],
        );

        if ($pulse->wasRecentlyCreated) {
            InAppNotification::query()->create([
                'tenant_id' => $tenant->id,
                'user_id' => $student->id,
                'title' => 'Quick check-in — how are we doing?',
                'body' => 'One question, ten seconds. Your answer goes straight to the people who can act on it.',
                'url' => '/checkin',
            ]);
        }
    }

    private function progressPct(BatchMember $member): int
    {
        $courseId = $member->batch?->course_id;
        if ($courseId === null) {
            return 0;
        }

        $topicIds = Topic::query()
            ->whereHas('module', fn ($q) => $q->where('course_id', $courseId))
            ->pluck('id');

        if ($topicIds->isEmpty()) {
            return 0;
        }

        $done = TopicCompletion::query()
            ->where('user_id', $member->user_id)
            ->whereIn('topic_id', $topicIds)
            ->count();

        return (int) round($done / $topicIds->count() * 100);
    }

    /**
     * @return Collection<int, User>
     */
    private function counselors(Tenant $tenant)
    {
        return User::query()
            ->where('tenant_id', $tenant->id)
            ->where('user_type', 'staff')
            ->whereHas('roles', fn ($q) => $q->where('slug', 'counselor'))
            ->get();
    }
}
