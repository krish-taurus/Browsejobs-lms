<?php

declare(strict_types=1);

use App\Actions\Funnel\GenerateWeeklyMasterclasses;
use App\Enums\BatchType;
use App\Enums\LiveSessionStatus;
use App\Models\Batch;
use App\Models\Course;
use App\Models\Lead;
use App\Models\LiveSession;
use App\Models\SessionChange;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Zoom\FakeZoomClient;
use App\Support\Zoom\ZoomClient;
use Illuminate\Support\Carbon;

beforeEach(function () {
    app()->instance(ZoomClient::class, new FakeZoomClient);
});

it('plans a masterclass in advance with a concrete time slot', function () {
    $tenant = Tenant::factory()->create();
    $course = Course::factory()->for($tenant)->create(['slug' => 'plan-course', 'code' => 'PC', 'name' => 'Plan Course']);

    $date = Carbon::today()->addDays(10)->toDateString();

    $this->artisan('masterclass:plan', ['course' => 'plan-course', 'date' => $date, 'time' => '11:30', '--capacity' => 50])
        ->assertSuccessful();

    withinTenant($tenant, function () use ($course, $date): void {
        $batch = Batch::query()
            ->where('course_id', $course->id)
            ->where('type', BatchType::Masterclass->value)
            ->first();

        expect($batch)->not->toBeNull()
            ->and($batch->capacity)->toBe(50)
            ->and(Carbon::parse($batch->starts_on)->toDateString())->toBe($date);

        $session = $batch->liveSessions()->first();
        expect($session)->not->toBeNull()
            ->and($session->title)->toBe('Masterclass — Plan Course')
            ->and($session->scheduled_start->format('Y-m-d H:i'))->toBe($date.' 11:30');
    });

    // Planning again neither duplicates the batch nor the class.
    $this->artisan('masterclass:plan', ['course' => 'plan-course', 'date' => $date, 'time' => '15:00'])
        ->assertSuccessful();

    withinTenant($tenant, function () use ($course): void {
        expect(Batch::query()->where('course_id', $course->id)->count())->toBe(1)
            ->and(LiveSession::query()->count())->toBe(1);
    });
});

it('reschedules a masterclass, moving the batch and logging the change', function () {
    $tenant = Tenant::factory()->create();
    $course = Course::factory()->for($tenant)->create(['slug' => 'resched-course', 'code' => 'RC']);

    $date = Carbon::today()->addDays(5)->toDateString();
    $this->artisan('masterclass:plan', ['course' => 'resched-course', 'date' => $date, 'time' => '10:00'])->assertSuccessful();

    $newDate = Carbon::today()->addDays(12)->toDateString();

    withinTenant($tenant, function () use ($course, $newDate): void {
        $batch = Batch::query()->where('course_id', $course->id)->first();

        $this->artisan('masterclass:reschedule', [
            'batch' => $batch->number, 'date' => $newDate, 'time' => '14:00', '--reason' => 'Trainer travel',
        ])->assertSuccessful();

        $batch = $batch->fresh();
        expect(Carbon::parse($batch->starts_on)->toDateString())->toBe($newDate);

        $session = $batch->liveSessions()->first();
        expect($session->scheduled_start->format('Y-m-d H:i'))->toBe($newDate.' 14:00')
            ->and(SessionChange::query()->where('live_session_id', $session->id)->value('type'))->toBe('rescheduled');
    });
});

it('cancels a masterclass and the generator respects the cancelled date', function () {
    $tenant = Tenant::factory()->create();
    $course = Course::factory()->for($tenant)->create(['slug' => 'cancel-course', 'code' => 'CC', 'position' => 1]);

    withinTenant($tenant, function () use ($tenant, $course): void {
        // Interest exists: a booked lead with a registered account.
        User::factory()->for($tenant)->create(['user_type' => 'student', 'phone' => '+919000033331', 'email' => 'c@example.com']);
        Lead::query()->create([
            'tenant_id' => $tenant->id, 'lead_type' => 'masterclass', 'name' => 'Cancel Test',
            'phone' => '+919000033331', 'phone_normalized' => '919000033331',
            'email' => 'c@example.com', 'course_slug' => 'cancel-course',
            'consented_at' => now(), 'consent_version' => 'v1',
        ]);

        // Generator seats them in this Saturday's masterclass.
        app(GenerateWeeklyMasterclasses::class)->handle();
        $batch = Batch::query()->where('course_id', $course->id)->first();
        expect($batch->members()->count())->toBe(1);

        $this->artisan('masterclass:cancel', ['batch' => $batch->number, '--reason' => 'Trainer sick'])
            ->assertSuccessful();

        $batch = $batch->fresh();
        expect($batch->status)->toBe('cancelled');

        // Generator must NOT recreate a batch on the cancelled date...
        $result = app(GenerateWeeklyMasterclasses::class)->handle();
        expect(Batch::query()->where('course_id', $course->id)->where('status', 'active')->count())->toBe(0);

        // ...but the following weekend, the same student is re-invited.
        $nextWeekend = Carbon::parse($batch->starts_on)->addDay();
        $result = app(GenerateWeeklyMasterclasses::class)->handle($nextWeekend->copy()->addDays(2));
        expect($result['enrolled'])->toBe(1);
    });
});
