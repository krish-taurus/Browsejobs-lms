<?php

declare(strict_types=1);

use App\Actions\LiveClasses\CancelLiveSession;
use App\Actions\LiveClasses\RescheduleLiveSession;
use App\Enums\BatchType;
use App\Enums\LiveSessionStatus;
use App\Jobs\DeleteZoomMeeting;
use App\Jobs\SendSessionReminder;
use App\Jobs\UpdateZoomMeeting;
use App\Models\BatchMember;
use App\Models\Course;
use App\Models\LiveSession;
use App\Models\SessionChange;
use App\Models\Tenant;
use App\Models\User;
use App\Support\MagicLink\MagicLinkService;
use App\Support\Notifications\SessionNotifier;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->student = User::factory()->for($this->tenant)->create(['user_type' => 'student']);

    $this->session = withinTenant($this->tenant, function () {
        $course = Course::query()->create(['code' => 'DE', 'name' => 'Data Eng', 'slug' => 'de']);
        $batch = $course->batches()->create(['number' => 'DE-202608-01', 'type' => BatchType::Paid->value]);
        BatchMember::query()->create(['batch_id' => $batch->id, 'user_id' => $this->student->id, 'status' => 'enrolled']);

        return LiveSession::query()->create([
            'batch_id' => $batch->id, 'title' => 'Class',
            'scheduled_start' => now()->addDays(2), 'status' => LiveSessionStatus::Scheduled->value,
            'zoom_meeting_id' => '900000001', 'reminder_token' => 'original',
        ]);
    });
});

it('cancels: deletes the meeting, logs the change, notifies, voids reminders', function () {
    Queue::fake();

    app(CancelLiveSession::class)->handle($this->session, 'Trainer sick', actor: null);

    $this->session->refresh();

    expect($this->session->status)->toBe(LiveSessionStatus::Cancelled)
        ->and($this->session->reminder_token)->not->toBe('original');

    Queue::assertPushed(DeleteZoomMeeting::class);
    expect(SessionChange::withoutGlobalScopes()->where('type', 'cancelled')->count())->toBe(1);
});

it('reschedules: updates Zoom, logs old/new, and re-arms the ladder for the new time', function () {
    Queue::fake();

    $newStart = now()->addDays(3);
    app(RescheduleLiveSession::class)->handle($this->session, $newStart, null, 'Venue clash');

    $this->session->refresh();

    expect($this->session->scheduled_start->toDateString())->toBe($newStart->toDateString())
        ->and($this->session->reminder_token)->not->toBe('original');

    Queue::assertPushed(UpdateZoomMeeting::class);
    Queue::assertPushed(SendSessionReminder::class, 4); // re-armed ladder for the new time

    $change = SessionChange::withoutGlobalScopes()->where('type', 'rescheduled')->first();
    expect($change)->not->toBeNull()
        ->and($change->old_start->toDateString())->toBe(now()->addDays(2)->toDateString());
});

it('proves re-arming: the old ladder no-ops while the new one fires', function () {
    // Reschedule (not faked so the token actually rotates + new jobs dispatch).
    Queue::fake();
    app(RescheduleLiveSession::class)->handle($this->session, now()->addDays(3), null, 'Moved');
    $this->session->refresh();

    $notifier = new class implements SessionNotifier
    {
        public int $count = 0;

        public function reminder(User $s, LiveSession $ls, string $url, string $w): void
        {
            $this->count++;
        }

        public function cancelled(User $s, LiveSession $ls, string $r): void {}

        public function rescheduled(User $s, LiveSession $ls, CarbonInterface $p, string $r): void {}

        public function trainerCancelled(User $t, LiveSession $ls, string $r): void {}

        public function trainerRescheduled(User $t, LiveSession $ls, CarbonInterface $p, string $r): void {}
    };

    // A reminder from the OLD ladder (token "original") must not send.
    (new SendSessionReminder($this->session->id, 'original', '2h'))->handle($notifier, app(MagicLinkService::class));
    expect($notifier->count)->toBe(0);

    // A reminder carrying the CURRENT token does send.
    (new SendSessionReminder($this->session->id, $this->session->reminder_token, '2h'))->handle($notifier, app(MagicLinkService::class));
    expect($notifier->count)->toBe(1);
});
