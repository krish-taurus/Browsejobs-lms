<?php

declare(strict_types=1);

use App\Models\Course;
use App\Models\PlacementStory;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->tenant = Tenant::factory()->create();
    $this->admin = User::factory()->for($this->tenant)->create(['user_type' => 'staff']);
    $this->admin->assignRole('admin');
    withinTenant($this->tenant, function () {
        Course::factory()->for($this->tenant)->create(['slug' => 'data-engineering', 'name' => 'DE']);
        Course::factory()->for($this->tenant)->create(['slug' => 'devops-cloud', 'name' => 'DevOps']);
    });
});

function csvUpload(string $body): UploadedFile
{
    return UploadedFile::fake()->createWithContent('stories.csv', $body);
}

it('downloads a CSV template with the header row', function () {
    Sanctum::actingAs($this->admin);

    $res = getJson('/api/v1/admin/placement-stories/template')->assertOk();
    expect($res->headers->get('content-type'))->toContain('text/csv')
        ->and($res->getContent())->toContain('course_slug,student_name,before_label');
});

it('bulk-imports stories: consented ones publish, others stay draft', function () {
    Sanctum::actingAs($this->admin);

    $csv = "course_slug,student_name,before_label,after_role,package_label,company_name,rounds,quote,consent\n"
        ."data-engineering,Aarti Sharma,Support engineer,Data Engineer,₹12 LPA,Acme,4,Cleared it!,yes\n"
        ."devops-cloud,Rahul Verma,Fresher,DevOps Engineer,₹9 LPA,Cloudspire,3,Got it,no\n";

    $this->post('/api/v1/admin/placement-stories/import', ['file' => csvUpload($csv)])
        ->assertCreated()
        ->assertJsonPath('data.created', 2)
        ->assertJsonPath('data.published', 1);

    $rows = PlacementStory::withoutGlobalScopes()->get();
    expect($rows)->toHaveCount(2);
    $aarti = $rows->firstWhere('student_name', 'Aarti Sharma');
    $rahul = $rows->firstWhere('student_name', 'Rahul Verma');
    expect($aarti->is_published)->toBeTrue()->and($aarti->consent)->toBeTrue()->and($aarti->is_sample)->toBeFalse()
        ->and($rahul->is_published)->toBeFalse()->and($rahul->consent)->toBeFalse();
});

it('retires a course sample once a real consented story is imported for it', function () {
    $de = Course::withoutGlobalScopes()->where('slug', 'data-engineering')->first();
    withinTenant($this->tenant, fn () => PlacementStory::query()->create([
        'tenant_id' => $this->tenant->id, 'course_id' => $de->id, 'student_name' => 'Sample One',
        'before_label' => 'x', 'after_role' => 'Data Engineer', 'is_published' => true, 'is_sample' => true, 'consent' => false,
    ]));

    Sanctum::actingAs($this->admin);
    $csv = "course_slug,student_name,before_label,after_role,consent\n"
        ."data-engineering,Real Person,Support,Data Engineer,yes\n";
    $this->post('/api/v1/admin/placement-stories/import', ['file' => csvUpload($csv)])->assertCreated();

    // The sample is unpublished now that a real, consented story exists.
    expect(PlacementStory::withoutGlobalScopes()->where('student_name', 'Sample One')->first()->is_published)->toBeFalse();
});

it('skips a row with an unknown course_slug and reports it', function () {
    Sanctum::actingAs($this->admin);
    $csv = "course_slug,student_name,before_label,after_role,consent\n"
        ."no-such-course,Ghost,Before,After,yes\n";

    $this->post('/api/v1/admin/placement-stories/import', ['file' => csvUpload($csv)])
        ->assertCreated()
        ->assertJsonPath('data.created', 0)
        ->assertJsonCount(1, 'data.skipped');

    expect(PlacementStory::withoutGlobalScopes()->count())->toBe(0);
});

it('rejects a file missing required columns', function () {
    Sanctum::actingAs($this->admin);
    $this->post('/api/v1/admin/placement-stories/import', ['file' => csvUpload("student_name\nBob\n")], ['Accept' => 'application/json'])
        ->assertStatus(422);
});

it('denies staff without manage-curriculum', function () {
    $support = User::factory()->for($this->tenant)->create(['user_type' => 'staff']);
    $support->assignRole('support-agent');
    Sanctum::actingAs($support);

    $this->post('/api/v1/admin/placement-stories/import', ['file' => csvUpload("course_slug,student_name,before_label,after_role\nx,y,z,w\n")])
        ->assertForbidden();
});
