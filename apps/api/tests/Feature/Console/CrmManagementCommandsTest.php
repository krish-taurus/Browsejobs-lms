<?php

declare(strict_types=1);

use App\Enums\BatchMemberStatus;
use App\Enums\BatchType;
use App\Enums\LiveSessionStatus;
use App\Models\Attendance;
use App\Models\Batch;
use App\Models\BatchMember;
use App\Models\Course;
use App\Models\LiveSession;
use App\Models\Message;
use App\Models\MessageTemplate;
use App\Models\Recording;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Zoom\FakeZoomClient;
use App\Support\Zoom\ZoomClient;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    app()->instance(ZoomClient::class, new FakeZoomClient);

    $this->tenant = Tenant::factory()->create();

    withinTenant($this->tenant, function (): void {
        foreach (['batch_credentials', 'batch_welcome', 'batch_announcement'] as $key) {
            foreach (['whatsapp', 'email'] as $channel) {
                MessageTemplate::query()->create([
                    'tenant_id' => $this->tenant->id, 'key' => $key, 'channel' => $channel,
                    'category' => 'utility',
                    'subject' => $channel === 'email' ? 'Test '.$key : null,
                    'body' => 'Hi {{name}} — {{batch}}',
                ]);
            }
        }

        $this->course = Course::factory()->for($this->tenant)->create(['slug' => 'crm-mgmt-course']);
        $this->batch = Batch::factory()->for($this->tenant)->create([
            'course_id' => $this->course->id, 'type' => BatchType::Paid->value, 'status' => 'active',
        ]);
    });
});

it('creates a batch of any type with a generated number', function () {
    $this->artisan('batch:create', [
        'course' => 'crm-mgmt-course', 'type' => 'paid',
        '--starts' => now()->addWeek()->toDateString(), '--capacity' => '25',
    ])->assertSuccessful();

    withinTenant($this->tenant, function (): void {
        $batch = Batch::query()->where('type', BatchType::Paid->value)->orderByDesc('id')->first();

        expect($batch->capacity)->toBe(25)
            ->and($batch->number)->toContain(strtoupper($this->course->code));
    });
});

it('updates capacity and status of a batch', function () {
    $this->artisan('batch:update', [
        'batch' => $this->batch->number, '--capacity' => '40', '--status' => 'completed',
    ])->assertSuccessful();

    withinTenant($this->tenant, function (): void {
        $fresh = Batch::query()->find($this->batch->id);

        expect($fresh->capacity)->toBe(40)->and($fresh->status)->toBe('completed');
    });
});

it('converts a batch to another type, keeping members and classes', function () {
    withinTenant($this->tenant, function (): void {
        $this->masterclass = Batch::factory()->for($this->tenant)->create([
            'course_id' => $this->course->id, 'type' => BatchType::Masterclass->value, 'status' => 'active',
        ]);
        $student = User::factory()->for($this->tenant)->create(['user_type' => 'student']);
        BatchMember::factory()->for($this->tenant)->create([
            'batch_id' => $this->masterclass->id, 'user_id' => $student->id,
            'status' => BatchMemberStatus::Enrolled->value,
        ]);
    });

    $this->artisan('batch:update', [
        'batch' => $this->masterclass->number, '--type' => 'paid',
    ])->assertSuccessful();

    withinTenant($this->tenant, function (): void {
        $fresh = Batch::query()->find($this->masterclass->id);

        expect($fresh->type)->toBe(BatchType::Paid)
            ->and($fresh->members()->count())->toBe(1);
    });
});

it('adds a brand-new student to a batch and sends OTP sign-in instructions', function () {
    $this->artisan('batch:add-student', [
        'batch' => (string) $this->batch->id, 'name' => 'New Joiner',
        '--phone' => '+919000088881', '--status' => 'enrolled', '--credentials' => true,
    ])->assertSuccessful();

    withinTenant($this->tenant, function (): void {
        $student = User::query()->where('phone', '+919000088881')->first();

        // Passwordless: the account is OTP-only, no password is generated.
        expect($student)->not->toBeNull()
            ->and($student->password)->toBeNull()
            ->and(BatchMember::query()->where('batch_id', $this->batch->id)->where('user_id', $student->id)->value('status'))->toBe(BatchMemberStatus::Enrolled)
            ->and(Message::query()->where('user_id', $student->id)->where('template_key', 'batch_credentials')->count())->toBe(2);
    });
});

it('treats re-adding an existing member as success and still sends the instructions', function () {
    // First add without the message — the student is seated silently.
    $this->artisan('batch:add-student', [
        'batch' => (string) $this->batch->id, 'name' => 'Re Added',
        '--phone' => '+919000088885', '--status' => 'enrolled',
    ])->assertSuccessful();

    // Second add (same phone) with --credentials: no duplicate membership, no
    // failure — and the sign-in instructions finally go out.
    $this->artisan('batch:add-student', [
        'batch' => (string) $this->batch->id, 'name' => 'Re Added',
        '--phone' => '+919000088885', '--status' => 'enrolled', '--credentials' => true,
    ])->assertSuccessful();

    withinTenant($this->tenant, function (): void {
        $student = User::query()->where('phone', '+919000088885')->first();

        expect(BatchMember::query()->where('batch_id', $this->batch->id)->where('user_id', $student->id)->count())->toBe(1)
            ->and(Message::query()->where('user_id', $student->id)->where('template_key', 'batch_credentials')->count())->toBe(2);
    });
});

it('drops a student and changes membership status', function () {
    withinTenant($this->tenant, function (): void {
        $this->student = User::factory()->for($this->tenant)->create(['user_type' => 'student']);
        BatchMember::factory()->for($this->tenant)->create([
            'batch_id' => $this->batch->id, 'user_id' => $this->student->id,
            'status' => BatchMemberStatus::Enrolled->value,
        ]);
    });

    $this->artisan('batch:member-status', [
        'batch' => (string) $this->batch->id, 'user' => (string) $this->student->id, 'status' => 'paused',
    ])->assertSuccessful();

    $this->artisan('batch:remove-student', [
        'batch' => (string) $this->batch->id, 'user' => (string) $this->student->id,
    ])->assertSuccessful();

    withinTenant($this->tenant, function (): void {
        expect(BatchMember::query()->where('batch_id', $this->batch->id)->where('user_id', $this->student->id)->value('status'))
            ->toBe(BatchMemberStatus::Dropped);
    });
});

it('announces a message to every occupying student', function () {
    withinTenant($this->tenant, function (): void {
        $this->student = User::factory()->for($this->tenant)->create(['user_type' => 'student', 'phone' => '+919000088882']);
        BatchMember::factory()->for($this->tenant)->create([
            'batch_id' => $this->batch->id, 'user_id' => $this->student->id,
            'status' => BatchMemberStatus::Enrolled->value,
        ]);
    });

    $this->artisan('batch:announce', [
        'batch' => $this->batch->number, 'message' => 'Class shifted 30 minutes earlier tomorrow.',
    ])->assertSuccessful();

    withinTenant($this->tenant, function (): void {
        expect(Message::query()->where('user_id', $this->student->id)->where('template_key', 'batch_announcement')->count())->toBe(2);
    });
});

it('reschedules and cancels a single class', function () {
    withinTenant($this->tenant, function (): void {
        $this->session = LiveSession::query()->create([
            'tenant_id' => $this->tenant->id, 'batch_id' => $this->batch->id, 'kind' => 'class',
            'title' => 'Movable Class', 'scheduled_start' => now()->addDays(2)->setTime(19, 0),
            'scheduled_end' => now()->addDays(2)->setTime(20, 30),
            'status' => LiveSessionStatus::Scheduled->value,
        ]);
    });

    $newDate = now()->addDays(3)->toDateString();

    $this->artisan('class:reschedule', [
        'session' => (string) $this->session->id, 'date' => $newDate, 'time' => '18:00',
    ])->assertSuccessful();

    withinTenant($this->tenant, function () use ($newDate): void {
        expect(LiveSession::query()->find($this->session->id)->scheduled_start->format('Y-m-d H:i'))
            ->toBe($newDate.' 18:00');
    });

    $this->artisan('class:cancel', ['session' => (string) $this->session->id])->assertSuccessful();

    withinTenant($this->tenant, function (): void {
        expect(LiveSession::query()->find($this->session->id)->status)->toBe(LiveSessionStatus::Cancelled);
    });
});

it('creates the LMS staff account on the fly for a CRM tech mentor', function () {
    $this->artisan('mentor:set', [
        'user' => 'new-mentor@crm.test',
        '--name' => 'CRM Tech Mentor',
        '--phone' => '+919000012399',
        '--headline' => 'DevOps Lead',
        '--expertise' => 'AWS,Docker',
    ])->assertSuccessful();

    withinTenant($this->tenant, function (): void {
        $user = User::query()->where('email', 'new-mentor@crm.test')->first();
        $profile = \App\Models\MentorProfile::query()->where('user_id', $user?->id)->first();

        expect($user)->not->toBeNull()
            ->and($user->user_type)->toBe('staff')
            ->and($user->phone)->toBe('+919000012399')
            ->and($profile)->not->toBeNull()
            ->and($profile->is_active)->toBeTrue();
    });
});

it('replaces a mentor weekly availability and can clear it', function () {
    $staff = withinTenant($this->tenant, function () {
        $user = User::factory()->for($this->tenant)->create(['user_type' => 'staff', 'email' => 'slots@staff.test']);
        \App\Models\MentorProfile::query()->create([
            'tenant_id' => $this->tenant->id, 'user_id' => $user->id,
            'expertise_tags' => ['Mentoring'], 'is_active' => true,
        ]);

        return $user;
    });

    $this->artisan('mentor:slots', [
        'user' => 'slots@staff.test',
        '--set' => 'Mon 18:00-20:00; Sat 10:00-13:00',
    ])->assertSuccessful();

    withinTenant($this->tenant, function () use ($staff): void {
        $windows = \App\Models\MentorAvailability::query()
            ->whereIn('mentor_profile_id', \App\Models\MentorProfile::query()->where('user_id', $staff->id)->select('id'))
            ->orderBy('weekday')
            ->get();

        // Monday = Carbon dayOfWeek 1, Saturday = 6; minutes from midnight IST.
        expect($windows)->toHaveCount(2)
            ->and($windows[0]->weekday)->toBe(1)
            ->and($windows[0]->start_minute)->toBe(18 * 60)
            ->and($windows[0]->end_minute)->toBe(20 * 60)
            ->and($windows[1]->weekday)->toBe(6);
    });

    $this->artisan('mentor:slots', ['user' => 'slots@staff.test', '--clear' => true])->assertSuccessful();

    withinTenant($this->tenant, function () use ($staff): void {
        expect(\App\Models\MentorAvailability::query()
            ->whereIn('mentor_profile_id', \App\Models\MentorProfile::query()->where('user_id', $staff->id)->select('id'))
            ->count())->toBe(0);
    });
});

it('adds a staff user to the mentor pool and can deactivate them', function () {
    $staff = withinTenant($this->tenant, fn () => User::factory()->for($this->tenant)->create([
        'user_type' => 'staff', 'email' => 'mentor@staff.test',
    ]));

    $this->artisan('mentor:set', [
        'user' => 'mentor@staff.test',
        '--headline' => 'Senior Data Engineer',
        '--expertise' => 'SQL, Spark, Interviews',
        '--courses' => $this->course->slug,
    ])->assertSuccessful();

    withinTenant($this->tenant, function () use ($staff): void {
        $profile = \App\Models\MentorProfile::query()->where('user_id', $staff->id)->first();

        expect($profile)->not->toBeNull()
            ->and($profile->headline)->toBe('Senior Data Engineer')
            ->and($profile->expertise_tags)->toBe(['SQL', 'Spark', 'Interviews'])
            ->and($profile->course_ids)->toBe([$this->course->id])
            ->and($profile->is_active)->toBeTrue();
    });

    // Update pass: deactivate without touching the rest of the profile.
    $this->artisan('mentor:set', ['user' => (string) $staff->id, '--active' => '0'])->assertSuccessful();

    withinTenant($this->tenant, function () use ($staff): void {
        $profile = \App\Models\MentorProfile::query()->where('user_id', $staff->id)->first();

        expect($profile->is_active)->toBeFalse()
            ->and($profile->headline)->toBe('Senior Data Engineer');
    });
});

it('schedules a batch mentoring session with the allocated host', function () {
    $mentor = withinTenant($this->tenant, fn () => User::factory()->for($this->tenant)->create([
        'user_type' => 'staff', 'email' => 'host@staff.test',
    ]));

    $this->artisan('class:schedule', [
        'batch' => $this->batch->number,
        'date' => now()->addDay()->toDateString(),
        'time' => '18:00',
        '--kind' => 'mentoring',
        '--host' => 'host@staff.test',
        '--no-announce' => true,
    ])->assertSuccessful();

    withinTenant($this->tenant, function () use ($mentor): void {
        $session = LiveSession::query()->where('batch_id', $this->batch->id)->latest('id')->first();

        expect($session->kind)->toBe('mentoring')
            ->and($session->host_user_id)->toBe($mentor->id)
            ->and($session->title)->toContain('Mentoring');
    });
});

it('schedules a weekday class series and announces the calendar once', function () {
    withinTenant($this->tenant, function (): void {
        $this->student = User::factory()->for($this->tenant)->create(['user_type' => 'student', 'phone' => '+919000088884']);
        BatchMember::factory()->for($this->tenant)->create([
            'batch_id' => $this->batch->id, 'user_id' => $this->student->id,
            'status' => BatchMemberStatus::Enrolled->value,
        ]);
    });

    // Next Monday through the second Friday: exactly 6 Mon/Wed/Fri slots.
    $start = now()->next('Monday');
    $until = $start->copy()->addDays(11);

    $this->artisan('class:schedule-series', [
        'batch' => $this->batch->number,
        '--weekdays' => '1,3,5',
        '--time' => '20:00',
        '--start' => $start->toDateString(),
        '--until' => $until->toDateString(),
    ])->assertSuccessful();

    withinTenant($this->tenant, function (): void {
        $sessions = LiveSession::query()->where('batch_id', $this->batch->id)->orderBy('scheduled_start')->get();

        expect($sessions)->toHaveCount(6)
            ->and($sessions->every(fn ($s) => in_array($s->scheduled_start->isoWeekday(), [1, 3, 5], true)))->toBeTrue()
            ->and($sessions->first()->scheduled_start->format('H:i'))->toBe('20:00')
            // ONE summary announcement per channel — not one per class.
            ->and(Message::query()->where('user_id', $this->student->id)->where('template_key', 'batch_announcement')->count())->toBe(2);
    });

    // Re-running the same range is a no-op — booked slots are skipped and the
    // series never spills past the end date.
    $this->artisan('class:schedule-series', [
        'batch' => $this->batch->number,
        '--weekdays' => '1,3,5',
        '--time' => '20:00',
        '--start' => $start->toDateString(),
        '--until' => $until->toDateString(),
        '--no-announce' => true,
    ])->assertSuccessful();

    withinTenant($this->tenant, function (): void {
        expect(LiveSession::query()->where('batch_id', $this->batch->id)->count())->toBe(6);
    });
});

it('attaches a recording to an ended class', function () {
    withinTenant($this->tenant, function (): void {
        $this->session = LiveSession::query()->create([
            'tenant_id' => $this->tenant->id, 'batch_id' => $this->batch->id, 'kind' => 'class',
            'title' => 'Recorded Class', 'scheduled_start' => now()->subDay(),
            'status' => 'ended',
        ]);
    });

    $this->artisan('class:recording', [
        'session' => (string) $this->session->id,
        'url' => 'https://zoom.us/rec/share/example',
        '--passcode' => 'abc123',
    ])->assertSuccessful();

    withinTenant($this->tenant, function (): void {
        $recording = Recording::query()->where('live_session_id', $this->session->id)->first();

        expect($recording)->not->toBeNull()
            ->and($recording->play_url)->toBe('https://zoom.us/rec/share/example')
            ->and($recording->status->value)->toBe('stored');
    });
});

it('marks a student present even when Zoom recorded nothing', function () {
    withinTenant($this->tenant, function (): void {
        $this->student = User::factory()->for($this->tenant)->create(['user_type' => 'student']);
        $this->session = LiveSession::query()->create([
            'tenant_id' => $this->tenant->id, 'batch_id' => $this->batch->id, 'kind' => 'class',
            'title' => 'Missed Join', 'scheduled_start' => now()->subDay(), 'status' => 'ended',
        ]);
    });

    $this->artisan('attendance:mark', [
        'session' => (string) $this->session->id, 'user' => (string) $this->student->id, 'pct' => '100',
    ])->assertSuccessful();

    withinTenant($this->tenant, function (): void {
        $row = Attendance::query()->where('live_session_id', $this->session->id)->where('user_id', $this->student->id)->first();

        expect($row)->not->toBeNull()->and($row->attended_pct)->toBe(100);
    });
});

it('updates a student profile and resends the sign-in instructions', function () {
    withinTenant($this->tenant, function (): void {
        $this->student = User::factory()->for($this->tenant)->create([
            'user_type' => 'student', 'phone' => '+919000088883', 'password' => 'OldPass1234',
        ]);
        BatchMember::factory()->for($this->tenant)->create([
            'batch_id' => $this->batch->id, 'user_id' => $this->student->id,
            'status' => BatchMemberStatus::Enrolled->value,
        ]);
    });

    $this->artisan('student:update', [
        'user' => (string) $this->student->id,
        '--name' => 'Renamed Student', '--active' => '0', '--send-login' => true,
    ])->assertSuccessful();

    withinTenant($this->tenant, function (): void {
        $fresh = User::query()->find($this->student->id);

        // Profile updated, instructions sent — and the password is untouched
        // (nothing ever changes or sends passwords).
        expect($fresh->name)->toBe('Renamed Student')
            ->and($fresh->is_active)->toBeFalse()
            ->and(Hash::check('OldPass1234', (string) $fresh->password))->toBeTrue()
            ->and(Message::query()->where('user_id', $fresh->id)->where('template_key', 'batch_credentials')->count())->toBe(2);
    });
});
