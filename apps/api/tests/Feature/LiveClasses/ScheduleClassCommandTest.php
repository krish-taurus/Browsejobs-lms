<?php

declare(strict_types=1);

use App\Enums\BatchMemberStatus;
use App\Enums\BatchType;
use App\Models\Batch;
use App\Models\BatchMember;
use App\Models\Course;
use App\Models\LiveSession;
use App\Models\Message;
use App\Models\MessageTemplate;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Zoom\FakeZoomClient;
use App\Support\Zoom\ZoomClient;

beforeEach(function () {
    app()->instance(ZoomClient::class, new FakeZoomClient);

    $this->tenant = Tenant::factory()->create();

    withinTenant($this->tenant, function (): void {
        foreach (['whatsapp', 'email'] as $channel) {
            MessageTemplate::query()->create([
                'tenant_id' => $this->tenant->id, 'key' => 'class_scheduled', 'channel' => $channel,
                'category' => 'utility',
                'subject' => $channel === 'email' ? 'New class: {{title}}' : null,
                'body' => 'Hi {{name}}, "{{title}}" on {{when}} for batch {{batch}} — {{link}}',
            ]);
        }

        $course = Course::factory()->for($this->tenant)->create();
        $this->batch = Batch::factory()->for($this->tenant)->create([
            'course_id' => $course->id, 'type' => BatchType::Bootcamp->value, 'status' => 'active',
        ]);
        $this->student = User::factory()->for($this->tenant)->create([
            'user_type' => 'student', 'phone' => '+919000066661',
        ]);
        BatchMember::factory()->for($this->tenant)->create([
            'batch_id' => $this->batch->id, 'user_id' => $this->student->id,
            'status' => BatchMemberStatus::Enrolled->value,
        ]);
    });
});

it('schedules a class by batch number and announces it to every student', function () {
    $this->artisan('class:schedule', [
        'batch' => $this->batch->number,
        'date' => now()->addDay()->toDateString(),
        'time' => '19:00',
        '--title' => 'Extra Doubt Class',
    ])->assertSuccessful();

    withinTenant($this->tenant, function (): void {
        $session = LiveSession::query()->where('batch_id', $this->batch->id)->first();

        expect($session)->not->toBeNull()
            ->and($session->title)->toBe('Extra Doubt Class')
            ->and(Message::query()->where('user_id', $this->student->id)->where('template_key', 'class_scheduled')->count())
            ->toBe(2); // WhatsApp + email
    });
});

it('also resolves the batch by id and can skip the announcement', function () {
    $this->artisan('class:schedule', [
        'batch' => (string) $this->batch->id,
        'date' => now()->addDay()->toDateString(),
        'time' => '10:00',
        '--no-announce' => true,
    ])->assertSuccessful();

    withinTenant($this->tenant, function (): void {
        expect(LiveSession::query()->where('batch_id', $this->batch->id)->count())->toBe(1)
            ->and(Message::query()->where('template_key', 'class_scheduled')->count())->toBe(0);
    });
});

it('refuses a class slot in the past', function () {
    $this->artisan('class:schedule', [
        'batch' => $this->batch->number,
        'date' => now()->subDay()->toDateString(),
        'time' => '10:00',
    ])->assertFailed();
});

it('fails cleanly for an unknown batch', function () {
    $this->artisan('class:schedule', [
        'batch' => 'NO-SUCH-BATCH',
        'date' => now()->addDay()->toDateString(),
        'time' => '10:00',
    ])->assertFailed();
});
