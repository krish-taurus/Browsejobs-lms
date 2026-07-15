<?php

declare(strict_types=1);

use App\Actions\LiveClasses\ScheduleLiveSession;
use App\Enums\BatchType;
use App\Enums\LiveSessionStatus;
use App\Jobs\SendSessionReminder;
use App\Models\BatchMember;
use App\Models\Course;
use App\Models\LiveSession;
use App\Models\MagicLink;
use App\Models\Tenant;
use App\Models\User;
use App\Support\MagicLink\MagicLinkService;
use App\Support\Notifications\SessionNotifier;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->batch = withinTenant($this->tenant, function () {
        $course = Course::query()->create(['code' => 'DE', 'name' => 'Data Eng', 'slug' => 'de']);

        return $course->batches()->create(['number' => 'DE-202608-01', 'type' => BatchType::Paid->value]);
    });
});

it('arms the full 12h/2h/5min ladder for a future session', function () {
    Queue::fake();

    app(ScheduleLiveSession::class)->handle($this->batch, 'Class', now()->addDays(2));

    Queue::assertPushed(SendSessionReminder::class, 3);

    foreach (['12h', '2h', '5min'] as $window) {
        Queue::assertPushed(SendSessionReminder::class, fn ($job) => $job->window === $window);
    }
});

it('skips reminder rungs whose time has already passed', function () {
    Queue::fake();

    // Only the 5-minute rung is still in the future for a class one hour out.
    app(ScheduleLiveSession::class)->handle($this->batch, 'Class', now()->addHour());

    Queue::assertPushed(SendSessionReminder::class, 1);
    Queue::assertPushed(SendSessionReminder::class, fn ($job) => $job->window === '5min');
});

it('sends each active member a magic dashboard link when the reminder fires', function () {
    $notifier = new class implements SessionNotifier
    {
        public array $reminded = [];

        public function reminder(User $s, LiveSession $ls, string $url, string $w): void
        {
            $this->reminded[] = ['user' => $s->id, 'url' => $url];
        }

        public function cancelled(User $s, LiveSession $ls, string $r): void {}

        public function rescheduled(User $s, LiveSession $ls, CarbonInterface $p, string $r): void {}
    };

    $active = User::factory()->for($this->tenant)->create(['user_type' => 'student']);
    $dropped = User::factory()->for($this->tenant)->create(['user_type' => 'student']);

    $session = withinTenant($this->tenant, function () use ($active, $dropped) {
        $session = LiveSession::query()->create([
            'batch_id' => $this->batch->id, 'title' => 'Class',
            'scheduled_start' => now()->addDay(), 'status' => LiveSessionStatus::Scheduled->value,
            'reminder_token' => 'tok-1',
        ]);
        BatchMember::query()->create(['batch_id' => $this->batch->id, 'user_id' => $active->id, 'status' => 'enrolled']);
        BatchMember::query()->create(['batch_id' => $this->batch->id, 'user_id' => $dropped->id, 'status' => 'dropped']);

        return $session;
    });

    (new SendSessionReminder($session->id, 'tok-1', '2h'))->handle($notifier, app(MagicLinkService::class));

    expect($notifier->reminded)->toHaveCount(1)
        ->and($notifier->reminded[0]['user'])->toBe($active->id)
        ->and($notifier->reminded[0]['url'])->toContain('/magic/')
        ->and(MagicLink::withoutGlobalScopes()->where('action', 'join-class')->count())->toBe(1);
});

it('no-ops when the reminder token is stale (session was re-armed)', function () {
    $notifier = fakeNotifier();
    $student = User::factory()->for($this->tenant)->create(['user_type' => 'student']);

    $session = withinTenant($this->tenant, function () use ($student) {
        $session = LiveSession::query()->create([
            'batch_id' => $this->batch->id, 'title' => 'Class',
            'scheduled_start' => now()->addDay(), 'status' => LiveSessionStatus::Scheduled->value,
            'reminder_token' => 'current-token',
        ]);
        BatchMember::query()->create(['batch_id' => $this->batch->id, 'user_id' => $student->id, 'status' => 'enrolled']);

        return $session;
    });

    (new SendSessionReminder($session->id, 'OLD-token', '2h'))->handle($notifier, app(MagicLinkService::class));

    expect($notifier->reminded)->toHaveCount(0);
});

function fakeNotifier(): SessionNotifier
{
    return new class implements SessionNotifier
    {
        public array $reminded = [];

        public function reminder(User $s, LiveSession $ls, string $url, string $w): void
        {
            $this->reminded[] = $s->id;
        }

        public function cancelled(User $s, LiveSession $ls, string $r): void {}

        public function rescheduled(User $s, LiveSession $ls, CarbonInterface $p, string $r): void {}
    };
}
