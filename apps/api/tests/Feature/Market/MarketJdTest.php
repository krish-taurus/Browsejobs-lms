<?php

declare(strict_types=1);

use App\Models\MarketJd;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AI\AiClient;
use App\Services\AI\FakeAiClient;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

beforeEach(function () {
    $this->fake = new FakeAiClient;
    app()->instance(AiClient::class, $this->fake);
    $this->tenant = Tenant::factory()->create();
    $this->seed(RolePermissionSeeder::class);
});

function marketOfficer(Tenant $tenant): User
{
    $user = User::factory()->for($tenant)->create(['user_type' => 'staff']);
    $user->assignRole('placement-officer');

    return $user;
}

function extractReply(array $skills, string $seniority = 'mid'): string
{
    return json_encode([
        'role_title' => 'Data Engineer',
        'seniority' => $seniority,
        'location' => 'Bengaluru',
        'skills' => $skills,
        'quality_score' => 80,
    ], JSON_THROW_ON_ERROR);
}

it('imports a JD, extracts skills via AI, and surfaces demand trends', function () {
    Sanctum::actingAs(marketOfficer($this->tenant));
    $this->fake->reply = extractReply(['python', 'sql', 'airflow']);

    postJson('/api/v1/admin/market-jds', [
        'role_title' => 'Data Engineer',
        'raw_jd' => 'Build ETL pipelines with Python and SQL and Airflow across the data platform.',
    ])->assertCreated()->assertJsonPath('data.imported', 1);

    $jd = MarketJd::withoutGlobalScopes()->sole();
    expect($jd->parse_status)->toBe('parsed')
        ->and($jd->extracted_skills)->toContain('python', 'sql', 'airflow');

    getJson('/api/v1/admin/market-jds')->assertOk()
        ->assertJsonPath('data.sample_size', 1)
        ->assertJsonPath('data.trends.0.skill', fn ($s) => in_array($s, ['python', 'sql', 'airflow'], true));
});

it('dedupes identical JDs on re-import', function () {
    Sanctum::actingAs(marketOfficer($this->tenant));
    $this->fake->reply = extractReply(['python']);
    $payload = ['role_title' => 'Data Engineer', 'raw_jd' => str_repeat('Python and SQL pipelines. ', 5)];

    postJson('/api/v1/admin/market-jds', $payload)->assertCreated()->assertJsonPath('data.imported', 1);
    postJson('/api/v1/admin/market-jds', $payload)->assertCreated()
        ->assertJsonPath('data.imported', 0)
        ->assertJsonPath('data.duplicates', 1);

    expect(MarketJd::withoutGlobalScopes()->count())->toBe(1);
});

it('falls back to a keyword scan when the model returns garbage', function () {
    Sanctum::actingAs(marketOfficer($this->tenant));
    $this->fake->reply = 'Sorry, I cannot do that.';

    postJson('/api/v1/admin/market-jds', [
        'role_title' => 'Data Engineer',
        'raw_jd' => 'We need strong Python, SQL and Spark for our data warehouse and ETL work.',
    ])->assertCreated();

    $jd = MarketJd::withoutGlobalScopes()->sole();
    expect($jd->parse_status)->toBe('parsed')
        ->and($jd->extracted_skills)->toContain('python', 'sql', 'spark');
});

it('imports a batch of JDs from a CSV', function () {
    Sanctum::actingAs(marketOfficer($this->tenant));
    $this->fake->reply = extractReply(['python', 'sql']);

    $csv = "role_title,raw_jd,company,seniority\n"
        ."Data Engineer,\"Python and SQL pipelines on AWS with Airflow orchestration.\",Acme,mid\n"
        ."Data Analyst,\"SQL and Power BI dashboards for business reporting teams.\",Globex,junior\n";
    $file = UploadedFile::fake()->createWithContent('jds.csv', $csv);

    postJson('/api/v1/admin/market-jds/import', ['file' => $file])->assertOk()
        ->assertJsonPath('data.imported', 2);

    expect(MarketJd::withoutGlobalScopes()->where('source', 'csv')->count())->toBe(2);
});

it('gates market intelligence behind the placement ability', function () {
    $student = User::factory()->for($this->tenant)->create(['user_type' => 'student']);
    Sanctum::actingAs($student);

    getJson('/api/v1/admin/market-jds')->assertForbidden();
    postJson('/api/v1/admin/market-jds', ['role_title' => 'X', 'raw_jd' => str_repeat('a', 50)])->assertForbidden();
});

it('does not leak market JDs across tenants', function () {
    $other = Tenant::factory()->create();
    MarketJd::factory()->for($other)->create();

    Sanctum::actingAs(marketOfficer($this->tenant));
    getJson('/api/v1/admin/market-jds')->assertOk()
        ->assertJsonPath('data.sample_size', 0)
        ->assertJsonPath('data.recent', []);
});

it('requires authentication', function () {
    getJson('/api/v1/admin/market-jds')->assertUnauthorized();
});
