<?php

declare(strict_types=1);

use App\Actions\LiveClasses\RescheduleLiveSession;
use App\Enums\BatchType;
use App\Enums\LiveSessionStatus;
use App\Models\Course;
use App\Models\LiveSession;
use App\Models\Message;
use App\Models\MessageTemplate;
use App\Models\Module;
use App\Models\Tenant;
use App\Models\Topic;
use App\Models\User;
use App\Support\Notifications\SessionNotifier;
use App\Support\WhatsApp\FakeWhatsAppClient;
use App\Support\WhatsApp\WhatsAppClient;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\postJson;
use function Pest\Laravel\putJson;

beforeEach(function () {
    Queue::fake();
    app()->instance(WhatsAppClient::class, new FakeWhatsAppClient);
    $this->seed(RolePermissionSeeder::class);
    $this->tenant = Tenant::factory()->create();
    MessageTemplate::factory()->for($this->tenant)->create(['key' => 'batch_allocation', 'channel' => 'whatsapp', 'body' => 'Hi {{name}}: {{batch}} — {{detail}}']);

    $this->admin = User::factory()->for($this->tenant)->create(['user_type' => 'staff', 'name' => 'Admin', 'phone' => '+91 90000 00001']);
    $this->admin->assignRole('admin');
    $this->anil = User::factory()->for($this->tenant)->create(['user_type' => 'staff', 'name' => 'Anil', 'phone' => '+91 90000 00002']);
    $this->anil->assignRole('trainer');
    $this->bela = User::factory()->for($this->tenant)->create(['user_type' => 'staff', 'name' => 'Bela', 'phone' => '+91 90000 00003']);
    $this->bela->assignRole('trainer');
    $this->carol = User::factory()->for($this->tenant)->create(['user_type' => 'staff', 'name' => 'Carol', 'phone' => '+91 90000 00004']);
    $this->carol->assignRole('mentor');

    [$this->batch, $this->pyspark] = withinTenant($this->tenant, function () {
        $course = Course::query()->create(['code' => 'DE', 'name' => 'DE', 'slug' => 'de']);
        $batch = $course->batches()->create(['number' => 'DE-1', 'type' => BatchType::Paid->value, 'trainer_id' => $this->anil->id]);
        $pyspark = Module::query()->create(['tenant_id' => $this->tenant->id, 'course_id' => $course->id, 'name' => 'PySpark', 'position' => 1]);

        return [$batch, $pyspark];
    });
});

function allocationMsgs(): int
{
    return Message::withoutGlobalScopes()->where('template_key', 'batch_allocation')->count();
}

it('allocates mentors and notifies newly-allocated staff', function () {
    Sanctum::actingAs($this->admin);

    putJson("/api/v1/admin/batches/{$this->batch->id}/module-trainers", [
        'lead_trainer_id' => $this->anil->id,
        'assignments' => [['module_id' => $this->pyspark->id, 'trainer_id' => $this->bela->id]],
        'mentor_ids' => [$this->carol->id],
    ])->assertOk()->assertJsonPath('data.mentor_ids', [$this->carol->id]);

    // Bela (new module trainer) + Carol (new mentor) get a message; Anil was already lead.
    expect(allocationMsgs())->toBe(2);
    expect(Message::withoutGlobalScopes()->where('template_key', 'batch_allocation')->where('user_id', $this->carol->id)->exists())->toBeTrue();
});

it('notifies every trainer, mentor and admin when a class is rescheduled', function () {
    withinTenant($this->tenant, function () {
        $this->batch->moduleTrainers()->create(['tenant_id' => $this->tenant->id, 'module_id' => $this->pyspark->id, 'user_id' => $this->bela->id]);
        $this->batch->batchMentors()->create(['tenant_id' => $this->tenant->id, 'user_id' => $this->carol->id]);
    });
    $session = withinTenant($this->tenant, function () {
        $topic = Topic::query()->create(['tenant_id' => $this->tenant->id, 'module_id' => $this->pyspark->id, 'name' => 'RDDs', 'position' => 1]);

        return LiveSession::query()->create([
            'batch_id' => $this->batch->id, 'topic_id' => $topic->id, 'title' => 'x',
            'scheduled_start' => now()->addDays(2), 'status' => LiveSessionStatus::Scheduled->value, 'reminder_token' => 't',
        ]);
    });

    $spy = Mockery::spy(SessionNotifier::class);
    app()->instance(SessionNotifier::class, $spy);

    withinTenant($this->tenant, fn () => app(RescheduleLiveSession::class)->handle($session, now()->addDays(3), now()->addDays(3)->addHour(), 'clash'));

    // Anil (lead) + Bela (module) + Carol (mentor) + Admin = 4 distinct staff.
    $spy->shouldHaveReceived('trainerRescheduled')->times(4);
});

it('lets an admin reschedule but forbids a plain trainer', function () {
    $session = withinTenant($this->tenant, fn () => LiveSession::query()->create([
        'batch_id' => $this->batch->id, 'title' => 'x',
        'scheduled_start' => now()->addDays(2), 'status' => LiveSessionStatus::Scheduled->value, 'reminder_token' => 't',
    ]));

    Sanctum::actingAs($this->anil); // trainer: has teach-classes, not manage-batches
    postJson("/api/v1/admin/sessions/{$session->id}/reschedule", [
        'scheduled_start' => now()->addDays(3)->toISOString(), 'reason' => 'x',
    ])->assertForbidden();

    Sanctum::actingAs($this->admin);
    postJson("/api/v1/admin/sessions/{$session->id}/reschedule", [
        'scheduled_start' => now()->addDays(3)->toISOString(), 'reason' => 'venue clash',
    ])->assertOk();
});
