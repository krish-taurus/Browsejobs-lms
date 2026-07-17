<?php

declare(strict_types=1);

use App\Actions\LiveClasses\ArmSessionReminders;
use App\Actions\Reports\GenerateCounselorDigest;
use App\Actions\Reports\GenerateTrainerBrief;
use App\Enums\ReportType;
use App\Jobs\SendTrainerBrief;
use App\Models\AiEvent;
use App\Models\Batch;
use App\Models\BatchMember;
use App\Models\Course;
use App\Models\LiveSession;
use App\Models\Message;
use App\Models\MessageTemplate;
use App\Models\Module;
use App\Models\Report;
use App\Models\StudentScore;
use App\Models\Tenant;
use App\Models\Topic;
use App\Models\User;
use App\Services\AI\AiClient;
use App\Services\AI\FakeAiClient;
use App\Support\WhatsApp\FakeWhatsAppClient;
use App\Support\WhatsApp\WhatsAppClient;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;

beforeEach(function () {
    $this->fake = new FakeAiClient;
    app()->instance(AiClient::class, $this->fake);
    app()->instance(WhatsAppClient::class, new FakeWhatsAppClient);
    $this->tenant = Tenant::factory()->create();
    MessageTemplate::factory()->for($this->tenant)->create(['key' => 'counselor_digest', 'channel' => 'whatsapp', 'body' => '{{name}}: {{body}}']);
    MessageTemplate::factory()->for($this->tenant)->create(['key' => 'trainer_brief', 'channel' => 'whatsapp', 'body' => '{{name}}: {{body}}']);
});

/* ---- Counselor digest ---- */

it('generates a counselor digest from risk movers and bills the counselor', function () {
    $counselor = User::factory()->for($this->tenant)->create(['user_type' => 'staff']);
    $student = User::factory()->for($this->tenant)->create(['user_type' => 'student', 'name' => 'Ravi']);
    StudentScore::factory()->for($this->tenant)->create(['user_id' => $student->id, 'risk_dropout' => 80, 'red_flags' => ['low_engagement']]);
    $this->fake->reply = 'Call Ravi first — disengaged.';

    $report = app(GenerateCounselorDigest::class)->handle($counselor);

    expect($report->type)->toBe(ReportType::CounselorDigest)
        ->and(Message::withoutGlobalScopes()->where('template_key', 'counselor_digest')->count())->toBe(1)
        ->and(AiEvent::withoutGlobalScopes()->where('user_id', $counselor->id)->where('purpose', 'report')->exists())->toBeTrue();
});

it('skips the counselor digest when there is no risk', function () {
    $counselor = User::factory()->for($this->tenant)->create(['user_type' => 'staff']);

    expect(app(GenerateCounselorDigest::class)->handle($counselor))->toBeNull()
        ->and(Message::withoutGlobalScopes()->where('template_key', 'counselor_digest')->count())->toBe(0);
});

/* ---- Trainer brief ---- */

function briefSession(Tenant $tenant, User $trainer): LiveSession
{
    $course = Course::factory()->for($tenant)->create();
    $batch = Batch::factory()->for($tenant)->create(['course_id' => $course->id, 'trainer_id' => $trainer->id]);
    $student = User::factory()->for($tenant)->create(['user_type' => 'student', 'name' => 'Asha']);
    BatchMember::factory()->for($tenant)->create(['batch_id' => $batch->id, 'user_id' => $student->id, 'status' => 'enrolled']);
    StudentScore::factory()->for($tenant)->create(['user_id' => $student->id, 'risk_dropout' => 70]);
    $module = Module::factory()->for($tenant)->create(['course_id' => $course->id]);
    $topic = Topic::factory()->for($tenant)->create(['module_id' => $module->id, 'name' => 'Recursion']);

    return LiveSession::query()->create([
        'tenant_id' => $tenant->id, 'batch_id' => $batch->id, 'topic_id' => $topic->id, 'title' => 'Class',
        'scheduled_start' => now()->addHours(3), 'scheduled_end' => now()->addHours(4), 'status' => 'scheduled',
    ]);
}

it('delivers a trainer brief for the session and bills the trainer', function () {
    $trainer = User::factory()->for($this->tenant)->create(['user_type' => 'staff']);
    $session = briefSession($this->tenant, $trainer);
    $this->fake->reply = 'Watch Asha today; reinforce recursion basics.';

    $report = app(GenerateTrainerBrief::class)->handle($session);

    expect($report->type)->toBe(ReportType::TrainerBrief)
        ->and($report->data['topic'])->toBe('Recursion')
        ->and(Message::withoutGlobalScopes()->where('template_key', 'trainer_brief')->count())->toBe(1)
        ->and(AiEvent::withoutGlobalScopes()->where('user_id', $trainer->id)->where('purpose', 'report')->exists())->toBeTrue();
});

it('skips the brief when the batch has no trainer', function () {
    $course = Course::factory()->for($this->tenant)->create();
    $batch = Batch::factory()->for($this->tenant)->create(['course_id' => $course->id, 'trainer_id' => null]);
    $session = LiveSession::query()->create([
        'tenant_id' => $this->tenant->id, 'batch_id' => $batch->id, 'title' => 'Class',
        'scheduled_start' => now()->addHours(3), 'scheduled_end' => now()->addHours(4), 'status' => 'scheduled',
    ]);

    expect(app(GenerateTrainerBrief::class)->handle($session))->toBeNull();
});

it('arms the brief on the reminder rail and a stale token no-ops', function () {
    Carbon::setTestNow('2026-07-17 09:00:00');
    $trainer = User::factory()->for($this->tenant)->create(['user_type' => 'staff']);
    $session = briefSession($this->tenant, $trainer); // starts 12:00

    Bus::fake();
    $token = withinTenant($this->tenant, fn () => app(ArmSessionReminders::class)->handle($session));

    // Armed at scheduled_start − 30min = 11:30.
    Bus::assertDispatched(SendTrainerBrief::class, fn ($job) => $job->liveSessionId === $session->id && $job->token === $token);

    // A stale token (after reschedule) → the job is a no-op.
    $this->fake->reply = 'brief';
    (new SendTrainerBrief($session->id, 'stale-token'))->handle(app(GenerateTrainerBrief::class));
    expect(Report::withoutGlobalScopes()->where('type', 'trainer_brief')->count())->toBe(0);

    Carbon::setTestNow();
});
