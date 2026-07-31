<?php

declare(strict_types=1);

use App\Actions\Funnel\CompleteEndedBootcamps;
use App\Actions\Funnel\GenerateWeeklyMasterclasses;
use App\Actions\Funnel\RolloverMasterclassesToBootcamps;
use App\Enums\AccessBlockLevel;
use App\Enums\BatchMemberStatus;
use App\Enums\BatchType;
use App\Models\AccessBlock;
use App\Models\Batch;
use App\Models\BatchMember;
use App\Models\Course;
use App\Models\Lead;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Zoom\FakeZoomClient;
use App\Support\Zoom\ZoomClient;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    app()->instance(ZoomClient::class, new FakeZoomClient);
});

it('creates the Saturday masterclass batch from course-interest leads and enrols registered students', function () {
    $tenant = Tenant::factory()->create();

    withinTenant($tenant, function () use ($tenant): void {
        $course = Course::factory()->for($tenant)->create(['slug' => 'data-eng', 'code' => 'DE']);

        $registered = User::factory()->for($tenant)->create([
            'user_type' => 'student', 'phone' => '+91 90000 11111', 'email' => 'booked@example.com',
        ]);

        // Booked the masterclass on the site (lead), then registered an account.
        Lead::query()->create([
            'tenant_id' => $tenant->id, 'lead_type' => 'masterclass', 'name' => 'Booked Student',
            'phone' => '+919000011111', 'phone_normalized' => '919000011111',
            'email' => 'booked@example.com', 'course_slug' => 'data-eng', 'consented_at' => now(), 'consent_version' => 'v1',
        ]);

        // Booked but never registered: an account is auto-created (OTP login,
        // no password) so they get seated too.
        Lead::query()->create([
            'tenant_id' => $tenant->id, 'lead_type' => 'masterclass', 'name' => 'No Account',
            'phone' => '+919000099999', 'phone_normalized' => '919000099999',
            'email' => 'noaccount@example.com', 'course_slug' => 'data-eng', 'consented_at' => now(), 'consent_version' => 'v1',
        ]);

        $result = app(GenerateWeeklyMasterclasses::class)->handle();

        expect($result)->toBe(['batches' => 1, 'enrolled' => 2]);

        $autoCreated = User::query()->where('phone', '+919000099999')->first();
        expect($autoCreated)->not->toBeNull()
            ->and($autoCreated->user_type)->toBe('student')
            ->and($autoCreated->name)->toBe('No Account');

        $batch = Batch::query()
            ->where('course_id', $course->id)
            ->where('type', BatchType::Masterclass->value)
            ->first();

        expect($batch)->not->toBeNull()
            ->and(Carbon::parse($batch->starts_on)->isSaturday())->toBeTrue()
            ->and($batch->members()->where('user_id', $registered->id)->value('status'))
            ->toBe(BatchMemberStatus::Enrolled)
            // The class itself is auto-scheduled at the default slot.
            ->and($batch->liveSessions()->count())->toBe(1)
            ->and($batch->liveSessions()->first()->scheduled_start->format('H:i'))
            ->toBe(config('funnel.masterclass_time'));

        // Idempotent: a second run creates nothing new.
        expect(app(GenerateWeeklyMasterclasses::class)->handle())
            ->toBe(['batches' => 0, 'enrolled' => 0]);
    });
});

it('spreads masterclasses for different courses across Saturday and Sunday', function () {
    $tenant = Tenant::factory()->create();

    withinTenant($tenant, function () use ($tenant): void {
        $courseA = Course::factory()->for($tenant)->create(['slug' => 'course-a', 'code' => 'CA', 'position' => 1]);
        $courseB = Course::factory()->for($tenant)->create(['slug' => 'course-b', 'code' => 'CB', 'position' => 2]);

        foreach ([['course-a', '+919000022221', 'a@example.com'], ['course-b', '+919000022222', 'b@example.com']] as [$slug, $phone, $email]) {
            User::factory()->for($tenant)->create(['user_type' => 'student', 'phone' => $phone, 'email' => $email]);
            Lead::query()->create([
                'tenant_id' => $tenant->id, 'lead_type' => 'masterclass', 'name' => $slug.' student',
                'phone' => $phone, 'phone_normalized' => preg_replace('/\D+/', '', $phone),
                'email' => $email, 'course_slug' => $slug, 'consented_at' => now(), 'consent_version' => 'v1',
            ]);
        }

        $result = app(GenerateWeeklyMasterclasses::class)->handle();
        expect($result)->toBe(['batches' => 2, 'enrolled' => 2]);

        $dayFor = fn (Course $course) => Carbon::parse(
            Batch::query()->where('course_id', $course->id)->where('type', BatchType::Masterclass->value)->value('starts_on')
        );

        expect($dayFor($courseA)->isSaturday())->toBeTrue()
            ->and($dayFor($courseB)->isSunday())->toBeTrue();
    });
});

it('rolls a finished masterclass into a 7-day bootcamp with its paid batch pre-linked', function () {
    $tenant = Tenant::factory()->create();

    withinTenant($tenant, function () use ($tenant): void {
        $course = Course::factory()->for($tenant)->create(['code' => 'DE']);
        $lastSaturday = Carbon::today()->previous(Carbon::SATURDAY);

        $masterclass = Batch::factory()->for($tenant)->create([
            'course_id' => $course->id,
            'type' => BatchType::Masterclass->value,
            'status' => 'active',
            'starts_on' => $lastSaturday->toDateString(),
            'ends_on' => $lastSaturday->toDateString(),
        ]);

        $student = User::factory()->for($tenant)->create(['user_type' => 'student']);
        BatchMember::factory()->for($tenant)->create([
            'batch_id' => $masterclass->id, 'user_id' => $student->id,
            'status' => BatchMemberStatus::Enrolled->value,
        ]);

        $result = app(RolloverMasterclassesToBootcamps::class)->handle();

        expect($result)->toBe(['rolled' => 1, 'enrolled' => 1])
            ->and($masterclass->fresh()->status)->toBe('completed');

        $bootcamp = Batch::query()
            ->where('course_id', $course->id)
            ->where('type', BatchType::Bootcamp->value)
            ->first();

        expect($bootcamp)->not->toBeNull()
            ->and($bootcamp->linked_source_batch_id)->toBe($masterclass->id)
            ->and(Carbon::parse($bootcamp->starts_on)->toDateString())->toBe($lastSaturday->copy()->addDays(2)->toDateString())
            ->and(Carbon::parse($bootcamp->starts_on)->diffInDays(Carbon::parse($bootcamp->ends_on)))->toEqual(6.0)
            ->and($bootcamp->members()->where('user_id', $student->id)->value('status'))->toBe(BatchMemberStatus::Enrolled);

        // The conversion target exists already, linked to the bootcamp.
        $paid = Batch::query()
            ->where('type', BatchType::Paid->value)
            ->where('linked_source_batch_id', $bootcamp->id)
            ->first();

        expect($paid)->not->toBeNull()
            // All 7 daily bootcamp classes are auto-scheduled with reminders.
            ->and($bootcamp->liveSessions()->count())->toBe(7);
    });
});

it('completes an ended bootcamp and reserves each attendee in the paid batch', function () {
    $tenant = Tenant::factory()->create();

    ['bootcamp' => $bootcamp, 'paid' => $paid, 'student' => $student] = bootcampConversionSetup($tenant);

    $bootcamp->update([
        'status' => 'active',
        'starts_on' => Carbon::today()->subDays(8)->toDateString(),
        'ends_on' => Carbon::today()->subDay()->toDateString(),
    ]);

    withinTenant($tenant, function () use ($bootcamp, $paid, $student): void {
        config()->set('funnel.paid_class_fallback_count', 3);

        $result = app(CompleteEndedBootcamps::class)->handle();

        expect($result['completed'])->toBe(1)
            ->and($result['converted'])->toBe(1)
            ->and($bootcamp->fresh()->status)->toBe('completed')
            ->and($paid->members()->where('user_id', $student->id)->value('status'))
            ->toBe(BatchMemberStatus::Reserved)
            // The paid batch's weekday class series is auto-scheduled too.
            ->and($paid->liveSessions()->count())->toBe(3)
            ->and($paid->liveSessions()->get()->every(fn ($s) => $s->scheduled_start->isWeekday()))->toBeTrue();
    });
});

it('blocks course content for hard-blocked students but not soft-blocked ones', function () {
    $tenant = Tenant::factory()->create();
    $student = User::factory()->for($tenant)->create(['user_type' => 'student']);

    $block = AccessBlock::factory()->for($tenant)->create([
        'user_id' => $student->id,
        'level' => AccessBlockLevel::Hard->value,
        'blocked_at' => now(),
        'lifted_at' => null,
    ]);

    Sanctum::actingAs($student);

    $this->getJson('/api/v1/me/labs')->assertForbidden();
    $this->getJson('/api/v1/me/grades')->assertForbidden();

    // Soft block only locks live classes/recordings — content stays reachable.
    $block->update(['level' => AccessBlockLevel::Soft->value]);
    $this->getJson('/api/v1/me/labs')->assertOk();

    // Lifting the hard block restores access.
    $block->update(['level' => AccessBlockLevel::Hard->value, 'lifted_at' => now()]);
    $this->getJson('/api/v1/me/labs')->assertOk();
});
