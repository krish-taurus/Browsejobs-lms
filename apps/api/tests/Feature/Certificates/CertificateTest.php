<?php

declare(strict_types=1);

use App\Actions\Certificates\IssueCertificate;
use App\Actions\Certificates\RevokeCertificate;
use App\Actions\Curriculum\MarkTopicCompleted;
use App\Jobs\RenderCertificate;
use App\Models\AuditLog;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\Message;
use App\Models\MessageTemplate;
use App\Models\Module;
use App\Models\Tenant;
use App\Models\Topic;
use App\Models\User;
use App\Support\Certificates\CertificateRenderer;
use App\Support\WhatsApp\FakeWhatsAppClient;
use App\Support\WhatsApp\WhatsAppClient;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

beforeEach(function () {
    $this->wa = new FakeWhatsAppClient;
    app()->instance(WhatsAppClient::class, $this->wa);
    Storage::fake('s3');
    $this->tenant = Tenant::factory()->create(['branding' => ['colors' => ['trust_blue' => '#1B6DF0'], 'fonts' => ['display' => 'Sora']]]);
    MessageTemplate::factory()->for($this->tenant)->create(['key' => 'certificate_ready', 'channel' => 'whatsapp', 'body' => 'Hi {{name}}, {{course}}: {{link}}']);
    $this->student = User::factory()->for($this->tenant)->create(['user_type' => 'student']);
});

function courseFor(Tenant $tenant): Course
{
    return Course::factory()->for($tenant)->create(['name' => 'Python Backend']);
}

/* ---- Issue + render + idempotency ---- */

it('issues a certificate with a unique code + number, audits, notifies, and renders', function () {
    $course = courseFor($this->tenant);

    $cert = app(IssueCertificate::class)->handle($this->student, $course);

    expect($cert->code)->toHaveLength(40)
        ->and($cert->number)->toBe('CERT-000001')
        ->and($cert->fresh()->status)->toBe('rendered') // RenderCertificate ran on the sync queue
        ->and(AuditLog::withoutGlobalScopes()->where('action', 'certificate.issued')->count())->toBe(1)
        ->and(Message::withoutGlobalScopes()->where('template_key', 'certificate_ready')->count())->toBe(1);

    $html = Storage::disk('s3')->get($cert->fresh()->storage_path);
    expect($html)->toContain('<svg')->toContain('/verify/'.$cert->code);
});

it('is idempotent — auto and manual issue converge on one certificate', function () {
    $course = courseFor($this->tenant);

    $a = app(IssueCertificate::class)->handle($this->student, $course);
    $b = app(IssueCertificate::class)->handle($this->student, $course, User::factory()->for($this->tenant)->create(['user_type' => 'staff']));

    expect($b->id)->toBe($a->id)
        ->and(Certificate::withoutGlobalScopes()->count())->toBe(1);
});

/* ---- Auto-issue on course completion ---- */

it('auto-issues when the last topic of the last module completes', function () {
    $course = courseFor($this->tenant);
    $module = Module::factory()->for($this->tenant)->create(['course_id' => $course->id]);
    $t1 = Topic::factory()->for($this->tenant)->create(['module_id' => $module->id]);
    $t2 = Topic::factory()->for($this->tenant)->create(['module_id' => $module->id]);

    app(MarkTopicCompleted::class)->handle($this->student, $t1);
    expect(Certificate::withoutGlobalScopes()->count())->toBe(0); // course not finished

    app(MarkTopicCompleted::class)->handle($this->student, $t2);
    expect(Certificate::withoutGlobalScopes()->where('user_id', $this->student->id)->where('course_id', $course->id)->count())->toBe(1);
});

it('does not auto-issue for a course with no topics', function () {
    $course = courseFor($this->tenant);
    Module::factory()->for($this->tenant)->create(['course_id' => $course->id]); // module, but no topics

    // Nothing to complete → no CourseCompleted, no cert (guarded).
    expect(Certificate::withoutGlobalScopes()->count())->toBe(0);
});

/* ---- Render job ---- */

it('render job is idempotent and stores the html', function () {
    $course = courseFor($this->tenant);
    $cert = Certificate::factory()->for($this->tenant)->create(['user_id' => $this->student->id, 'course_id' => $course->id, 'status' => 'pending']);

    (new RenderCertificate($cert->id))->handle(app(CertificateRenderer::class));
    (new RenderCertificate($cert->id))->handle(app(CertificateRenderer::class)); // no-op

    expect($cert->fresh()->status)->toBe('rendered');
    Storage::disk('s3')->assertExists($cert->fresh()->storage_path);
});

/* ---- Public verify (host-independent, no auth) ---- */

it('verifies a genuine certificate publicly, with no auth and no tenant host', function () {
    $course = courseFor($this->tenant);
    $cert = app(IssueCertificate::class)->handle($this->student, $course);

    getJson("/api/v1/verify/{$cert->code}")
        ->assertOk()
        ->assertJsonPath('data.valid', true)
        ->assertJsonPath('data.course', 'Python Backend')
        ->assertJsonPath('data.student_name', $this->student->name);
});

it('reports a revoked certificate as invalid', function () {
    $course = courseFor($this->tenant);
    $cert = app(IssueCertificate::class)->handle($this->student, $course);

    app(RevokeCertificate::class)->handle($cert, null);

    getJson("/api/v1/verify/{$cert->code}")
        ->assertOk()
        ->assertJsonPath('data.valid', false)
        ->assertJsonPath('data.revoked', true);
});

it('reports an unknown code as invalid without leaking existence', function () {
    getJson('/api/v1/verify/nope-does-not-exist')
        ->assertOk()
        ->assertJsonPath('data.valid', false)
        ->assertJsonMissingPath('data.student_name');
});

/* ---- Student + admin surfaces ---- */

it('lists the student their own certificates with a signed download', function () {
    $course = courseFor($this->tenant);
    app(IssueCertificate::class)->handle($this->student, $course);
    Sanctum::actingAs($this->student);

    getJson('/api/v1/me/certificates')
        ->assertOk()
        ->assertJsonPath('data.0.course', 'Python Backend')
        ->assertJsonPath('data.0.status', 'rendered');
});

it('rejects certificates for guests and other tenants', function () {
    getJson('/api/v1/me/certificates')->assertUnauthorized();
});

it('lets a roster manager manually issue and revoke, gated + audited', function () {
    $this->seed(RolePermissionSeeder::class);
    $course = courseFor($this->tenant);
    $admin = User::factory()->for($this->tenant)->create(['user_type' => 'staff']);
    $admin->assignRole('admin');
    Sanctum::actingAs($admin);

    $res = postJson('/api/v1/admin/certificates', ['user_id' => $this->student->id, 'course_id' => $course->id])
        ->assertCreated()
        ->assertJsonPath('data.course', 'Python Backend');

    $id = $res->json('data.id');
    postJson("/api/v1/admin/certificates/{$id}/revoke", [])->assertOk()->assertJsonPath('data.revoked', true);

    expect(AuditLog::withoutGlobalScopes()->where('action', 'certificate.revoked')->count())->toBe(1);
});

it('denies certificate management without manage-rosters', function () {
    $this->seed(RolePermissionSeeder::class);
    $counselor = counselorIn($this->tenant);
    Sanctum::actingAs($counselor);

    getJson('/api/v1/admin/certificates')->assertForbidden();
});
