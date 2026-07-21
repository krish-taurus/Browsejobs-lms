<?php

declare(strict_types=1);

use App\Actions\LiveClasses\CancelLiveSession;
use App\Actions\LiveClasses\RescheduleLiveSession;
use App\Enums\BatchType;
use App\Enums\LiveSessionStatus;
use App\Models\Course;
use App\Models\LiveSession;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Notifications\SessionNotifier;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();
    $this->tenant = Tenant::factory()->create();
    $this->trainer = User::factory()->for($this->tenant)->create(['user_type' => 'staff', 'name' => 'Coach Anil']);

    $this->session = withinTenant($this->tenant, function () {
        $course = Course::query()->create(['code' => 'DE', 'name' => 'Data Eng', 'slug' => 'de']);
        $batch = $course->batches()->create(['number' => 'DE-202608-01', 'type' => BatchType::Paid->value, 'trainer_id' => $this->trainer->id]);

        return LiveSession::query()->create([
            'batch_id' => $batch->id, 'title' => 'Class',
            'scheduled_start' => now()->addDays(2), 'status' => LiveSessionStatus::Scheduled->value,
            'reminder_token' => 'original',
        ]);
    });

    $this->spy = Mockery::spy(SessionNotifier::class);
    app()->instance(SessionNotifier::class, $this->spy);
});

it('notifies the trainer when their class is cancelled', function () {
    withinTenant($this->tenant, fn () => app(CancelLiveSession::class)->handle($this->session, 'Trainer sick'));

    $this->spy->shouldHaveReceived('trainerCancelled')
        ->withArgs(fn (User $t) => $t->id === $this->trainer->id)->once();
});

it('notifies the trainer when their class is rescheduled', function () {
    withinTenant($this->tenant, fn () => app(RescheduleLiveSession::class)->handle($this->session, now()->addDays(3), now()->addDays(3)->addHour(), 'Venue clash'));

    $this->spy->shouldHaveReceived('trainerRescheduled')
        ->withArgs(fn (User $t) => $t->id === $this->trainer->id)->once();
});

it('does not attempt a trainer notification when the batch has no trainer', function () {
    withinTenant($this->tenant, fn () => $this->session->batch->update(['trainer_id' => null]));

    withinTenant($this->tenant, fn () => app(CancelLiveSession::class)->handle($this->session, 'sick'));

    $this->spy->shouldNotHaveReceived('trainerCancelled');
});
