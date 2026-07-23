<?php

declare(strict_types=1);

use App\Events\ModuleCompleted;
use App\Listeners\SendModuleReport;
use App\Models\Attendance;
use App\Models\Batch;
use App\Models\BatchMember;
use App\Models\Course;
use App\Models\InAppNotification;
use App\Models\LiveSession;
use App\Models\Message;
use App\Models\MessageTemplate;
use App\Models\Module;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;

/**
 * Candidate Performance (ADR 0050): weighted score (attendance 50 / mocks 30 /
 * assignments 20), RAG bands, batch-wise ranking, and the per-module report.
 */
beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->tenant = Tenant::factory()->create();
    $this->admin = User::factory()->for($this->tenant)->create(['user_type' => 'staff']);
    $this->admin->assignRole('admin');
});

function perfStudent(Tenant $tenant, string $name, float $attendedPct): User
{
    $student = User::factory()->for($tenant)->create(['user_type' => 'student', 'name' => $name, 'phone' => '+9190000'.rand(10000, 99999)]);
    $batch = Batch::query()->firstOrCreate(
        ['tenant_id' => $tenant->id, 'number' => 'DE-TEST-01'],
        ['course_id' => Course::factory()->for($tenant)->create()->id, 'type' => 'paid', 'status' => 'active'],
    );
    BatchMember::query()->create([
        'tenant_id' => $tenant->id, 'batch_id' => $batch->id, 'user_id' => $student->id, 'status' => 'enrolled',
    ]);
    $session = LiveSession::query()->create([
        'tenant_id' => $tenant->id, 'batch_id' => $batch->id,
        'title' => 'Class', 'scheduled_start' => now()->subDay(),
    ]);
    Attendance::query()->create([
        'tenant_id' => $tenant->id, 'live_session_id' => $session->id, 'user_id' => $student->id,
        'total_seconds' => 3600, 'attended_pct' => $attendedPct,
    ]);

    return $student;
}

it('ranks candidates by the weighted score with RAG bands', function () {
    Sanctum::actingAs($this->admin);
    perfStudent($this->tenant, 'High Achiever', 100.0);
    perfStudent($this->tenant, 'At Risk', 40.0);

    $res = getJson('/api/v1/admin/candidate-performance')->assertOk()->json('data');

    expect($res['candidates'][0]['name'])->toBe('High Achiever')
        ->and($res['candidates'][0]['rank'])->toBe(1)
        // 100% attendance × 0.5 weight, no mocks/assignments yet → 50.0 = red band.
        ->and($res['candidates'][0]['total'])->toEqual(50)
        ->and($res['candidates'][0]['band'])->toBe('red')
        ->and($res['candidates'][1]['name'])->toBe('At Risk')
        ->and($res['weights']['attendance'])->toBe(0.5);
});

it('keeps candidate performance tenant-scoped and gated', function () {
    $other = Tenant::factory()->create();
    perfStudent($other, 'Other Tenant Student', 90.0);

    Sanctum::actingAs($this->admin);
    $res = getJson('/api/v1/admin/candidate-performance')->assertOk()->json('data');
    expect($res['candidates'])->toBe([]);

    // Staff without the students permission cannot see the dashboard.
    $support = User::factory()->for($this->tenant)->create(['user_type' => 'staff']);
    $support->assignRole('support-agent');
    Sanctum::actingAs($support);
    getJson('/api/v1/admin/candidate-performance')->assertForbidden();
});

it('sends the module report after a module completes', function () {
    Queue::fake();
    MessageTemplate::query()->create([
        'tenant_id' => $this->tenant->id, 'key' => 'module_report', 'channel' => 'whatsapp',
        'category' => 'utility', 'name' => 'Module report',
        'body' => '{{name}}, your {{module}} report: {{total}}/100 · attendance {{attendance}}% · assignments {{assignments}}. {{link}}',
    ]);
    $student = perfStudent($this->tenant, 'Asha', 85.0);
    $module = Module::factory()->for($this->tenant)->create([
        'course_id' => Course::factory()->for($this->tenant)->create()->id, 'name' => 'Python',
    ]);

    app(SendModuleReport::class)->handle(new ModuleCompleted($student, $module));

    $notification = InAppNotification::withoutGlobalScopes()->where('user_id', $student->id)->sole();
    expect($notification->title)->toContain('Python')
        ->and($notification->body)->toContain('attendance 85%');

    expect(Message::withoutGlobalScopes()->where('user_id', $student->id)->exists())->toBeTrue();
});
