<?php

declare(strict_types=1);

use App\Actions\Reports\GenerateWeeklyReport;
use App\Actions\Reports\RunWeeklyReports;
use App\Enums\ReportType;
use App\Jobs\GenerateWeeklyReportJob;
use App\Models\AiEvent;
use App\Models\Batch;
use App\Models\BatchMember;
use App\Models\Course;
use App\Models\InAppNotification;
use App\Models\Message;
use App\Models\MessageTemplate;
use App\Models\Module;
use App\Models\Report;
use App\Models\Tenant;
use App\Models\Topic;
use App\Models\User;
use App\Services\AI\AiClient;
use App\Services\AI\FakeAiClient;
use App\Support\WhatsApp\FakeWhatsAppClient;
use App\Support\WhatsApp\WhatsAppClient;
use Illuminate\Support\Facades\Bus;

beforeEach(function () {
    $this->fake = new FakeAiClient;
    app()->instance(AiClient::class, $this->fake);
    app()->instance(WhatsAppClient::class, new FakeWhatsAppClient);
    $this->tenant = Tenant::factory()->create();
    MessageTemplate::factory()->for($this->tenant)->create(['key' => 'weekly_report', 'channel' => 'whatsapp', 'body' => 'Hi {{name}}: {{link}}']);
});

/** An active (enrolled) student with a scored module. */
function activeStudent(Tenant $tenant): User
{
    $student = User::factory()->for($tenant)->create(['user_type' => 'student']);
    $course = Course::factory()->for($tenant)->create();
    $batch = Batch::factory()->for($tenant)->create(['course_id' => $course->id]);
    BatchMember::factory()->for($tenant)->create(['batch_id' => $batch->id, 'user_id' => $student->id, 'status' => 'enrolled']);
    $module = Module::factory()->for($tenant)->create(['course_id' => $course->id]);
    Topic::factory()->for($tenant)->create(['module_id' => $module->id]);

    return $student;
}

it('generates an AI-narrated report, delivers it, and bills the student', function () {
    $student = activeStudent($this->tenant);
    $this->fake->reply = 'You did great on loops. Work on recursion. Focus: finish your next lab.';

    $report = app(GenerateWeeklyReport::class)->handle($student);

    expect($report->type)->toBe(ReportType::WeeklyStudent)
        ->and($report->narrative)->toContain('great on loops')
        ->and($report->data)->toHaveKey('mastery')
        ->and(Message::withoutGlobalScopes()->where('template_key', 'weekly_report')->count())->toBe(1)
        ->and(InAppNotification::withoutGlobalScopes()->where('user_id', $student->id)->count())->toBe(1)
        ->and(AiEvent::withoutGlobalScopes()->where('user_id', $student->id)->where('purpose', 'report')->exists())->toBeTrue();
});

it('falls back to a templated narrative when the budget is exceeded, and still delivers', function () {
    config(['ai.daily_token_budget' => 1]);
    // Pre-spend the budget so the report call trips immediately.
    AiEvent::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenant->id, 'user_id' => ($student = activeStudent($this->tenant))->id,
        'purpose' => 'report', 'model' => 'x', 'prompt_tokens' => 5, 'completion_tokens' => 5,
        'total_tokens' => 10, 'cost_micros' => 0, 'latency_ms' => 1, 'status' => 'ok',
    ]);

    $report = app(GenerateWeeklyReport::class)->handle($student);

    expect($report->narrative)->not->toBeEmpty()
        ->and(AiEvent::withoutGlobalScopes()->where('user_id', $student->id)->where('status', 'ok')->count())->toBe(1) // no new ok call
        ->and(Message::withoutGlobalScopes()->where('template_key', 'weekly_report')->count())->toBe(1); // still delivered
});

it('skips a student who is not in an active batch', function () {
    $student = User::factory()->for($this->tenant)->create(['user_type' => 'student']);

    expect(app(GenerateWeeklyReport::class)->handle($student))->toBeNull()
        ->and(Report::withoutGlobalScopes()->count())->toBe(0);
});

it('is idempotent per week — no duplicate row, one message', function () {
    $student = activeStudent($this->tenant);
    $this->fake->reply = 'Report body.';

    $a = app(GenerateWeeklyReport::class)->handle($student);
    $b = app(GenerateWeeklyReport::class)->handle($student);

    expect($b->id)->toBe($a->id)
        ->and(Report::withoutGlobalScopes()->count())->toBe(1)
        ->and(Message::withoutGlobalScopes()->where('template_key', 'weekly_report')->count())->toBe(1);
});

it('queues a report for active students only, per tenant', function () {
    Bus::fake();
    $active = activeStudent($this->tenant);
    User::factory()->for($this->tenant)->create(['user_type' => 'student']); // idle, no batch

    $other = Tenant::factory()->create();
    activeStudent($other);

    $summary = withinTenant($this->tenant, fn () => app(RunWeeklyReports::class)->handle());

    // 2 active students across the two tenants (the idle one is excluded).
    expect($summary['queued'])->toBe(2);
    Bus::assertDispatched(GenerateWeeklyReportJob::class, fn ($job) => $job->studentId === $active->id);
});
