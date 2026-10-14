<?php

declare(strict_types=1);

use App\Enums\BatchMemberStatus;
use App\Enums\BatchType;
use App\Events\MockCompleted;
use App\Jobs\RunJobShortlistAgent;
use App\Models\Batch;
use App\Models\BatchMember;
use App\Models\Course;
use App\Models\CvProfile;
use App\Models\EmployerCandidate;
use App\Models\EmployerJob;
use App\Models\EmployerJobApplication;
use App\Models\EmployerMember;
use App\Models\EmployerWorkspace;
use App\Models\InterviewInvite;
use App\Models\JdMock;
use App\Models\JobShortlistEntry;
use App\Models\JobShortlistRun;
use App\Models\MockBlueprint;
use App\Models\MockInterview;
use App\Models\StudentScore;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Employers\InterviewTemplates;
use App\Support\Employers\ShortlistReport;
use App\Support\WhatsApp\FakeWhatsAppClient;
use App\Support\WhatsApp\WhatsAppClient;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    $this->recruiter = User::factory()->for($this->tenant)->create(['user_type' => 'employer']);
    $this->workspace = EmployerWorkspace::factory()->for($this->tenant)->create();
    EmployerMember::factory()->for($this->tenant)->create([
        'employer_workspace_id' => $this->workspace->id,
        'user_id' => $this->recruiter->id,
    ]);
    $this->job = EmployerJob::factory()->for($this->tenant)->published()->create([
        'employer_workspace_id' => $this->workspace->id,
        'created_by_id' => $this->recruiter->id,
        'title' => 'Data Engineer',
        'skills' => ['python', 'sql', 'airflow', 'aws'],
        'experience_min_years' => 2,
        'experience_max_years' => 6,
        'locations' => ['Bengaluru'],
    ]);
    $this->base = "/api/v1/employer/workspaces/{$this->workspace->id}";
});

/** A BrowseJobs student the existing talent matcher will surface for a Data Engineer JD. */
function shortlistStudent(Tenant $tenant, array $skills, int $pri = 60): User
{
    $student = User::factory()->for($tenant)->create(['user_type' => 'student']);
    CvProfile::query()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $student->id,
        'data' => ['skills' => $skills, 'summary' => 'Trained on the BrowseJobs programme.'],
        'cv_mock_completed_at' => now(),
    ]);
    StudentScore::query()->create(['tenant_id' => $tenant->id, 'user_id' => $student->id, 'pri' => $pri]);
    $course = Course::factory()->for($tenant)->create(['name' => 'Data Engineering']);
    $batch = Batch::factory()->for($tenant)->create(['course_id' => $course->id, 'type' => BatchType::Paid->value]);
    BatchMember::factory()->for($tenant)->create([
        'batch_id' => $batch->id,
        'user_id' => $student->id,
        'status' => BatchMemberStatus::Completed->value,
    ]);

    return $student;
}

function naukriCsv(array $rows): UploadedFile
{
    $lines = ['Name,E-mail ID,Mobile No.,Current Designation,Current Location,Total Experience,Key Skills'];
    foreach ($rows as $r) {
        $lines[] = implode(',', array_map(fn ($v) => '"'.str_replace('"', '""', (string) $v).'"', $r));
    }

    return UploadedFile::fake()->createWithContent('naukri-export.csv', "\xEF\xBB\xBF".implode("\n", $lines));
}

/** A real one-sheet .xlsx (shared strings), like a client's own database export. */
function clientXlsx(array $grid): UploadedFile
{
    $strings = [];
    $index = function (string $v) use (&$strings): int {
        $i = array_search($v, $strings, true);
        if ($i === false) {
            $strings[] = $v;
            $i = count($strings) - 1;
        }

        return $i;
    };
    $rowsXml = '';
    foreach ($grid as $r => $cells) {
        $rowsXml .= '<row r="'.($r + 1).'">';
        foreach (array_values($cells) as $c => $value) {
            $ref = chr(65 + $c).($r + 1);
            $rowsXml .= '<c r="'.$ref.'" t="s"><v>'.$index((string) $value).'</v></c>';
        }
        $rowsXml .= '</row>';
    }
    $shared = '<?xml version="1.0" encoding="UTF-8"?><sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';
    foreach ($strings as $s) {
        $shared .= '<si><t>'.htmlspecialchars($s, ENT_XML1).'</t></si>';
    }
    $shared .= '</sst>';

    $path = tempnam(sys_get_temp_dir(), 'xlsx');
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::OVERWRITE);
    $zip->addFromString('xl/sharedStrings.xml', $shared);
    $zip->addFromString('xl/worksheets/sheet1.xml', '<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'.$rowsXml.'</sheetData></worksheet>');
    $zip->close();

    return UploadedFile::fake()->createWithContent('client-database.xlsx', (string) file_get_contents($path));
}

it('imports a Naukri export, reads experience and skills, and reports skipped rows', function (): void {
    Sanctum::actingAs($this->recruiter);

    $response = $this->post("{$this->base}/candidate-sources", [
        'source' => 'naukri',
        'file' => naukriCsv([
            ['Asha Rao', 'asha@example.test', '+91 98450 11111', 'Data Engineer', 'Bengaluru', '5 Year(s) 3 Month(s)', 'Python, SQL, Airflow, Spark'],
            ['Ravi K', '', '9876501234', 'Analyst', 'Pune', '1 Year(s)', 'Excel, SQL'],
            ['No Contact', '', '', 'Data Engineer', 'Bengaluru', '3', 'Python'],
        ]),
    ], ['Accept' => 'application/json']);

    $response->assertCreated()
        ->assertJsonPath('data.counts.naukri', 2)
        ->assertJsonPath('data.import.created', 2)
        ->assertJsonPath('data.import.skipped', 1);
    expect($response->json('data.import.errors.0'))->toContain('no mobile number or email');

    $asha = EmployerCandidate::query()->where('name', 'Asha Rao')->firstOrFail();
    expect($asha->phone)->toBe('9845011111')
        ->and((float) $asha->experience_years)->toBe(5.3)
        ->and($asha->skills)->toBe(['Python', 'SQL', 'Airflow', 'Spark'])
        ->and($asha->employer_workspace_id)->toBe($this->workspace->id);

    // Re-uploading the same export updates people, never duplicates them.
    $this->post("{$this->base}/candidate-sources", [
        'source' => 'naukri',
        'file' => naukriCsv([['Asha Rao', 'asha@example.test', '9845011111', 'Senior Data Engineer', 'Bengaluru', '6', 'Python, SQL']]),
    ], ['Accept' => 'application/json'])->assertCreated()->assertJsonPath('data.import.updated', 1);

    expect(EmployerCandidate::query()->where('source', 'naukri')->count())->toBe(2)
        ->and($asha->fresh()->current_title)->toBe('Senior Data Engineer');
});

it('imports the client database from an Excel file', function (): void {
    Sanctum::actingAs($this->recruiter);

    $this->post("{$this->base}/candidate-sources", [
        'source' => 'client_db',
        'file' => clientXlsx([
            ['Our candidate list'],
            ['Candidate Name', 'Email', 'Phone', 'Skills', 'Experience', 'City'],
            ['Meera N', 'meera@example.test', '9000011111', 'python; aws; airflow', '4', 'Bengaluru'],
        ]),
    ], ['Accept' => 'application/json'])
        ->assertCreated()
        ->assertJsonPath('data.counts.client_db', 1);

    expect(EmployerCandidate::query()->where('source', 'client_db')->value('location'))->toBe('Bengaluru');
});

it('rejects an unreadable sheet with a clear message', function (): void {
    Sanctum::actingAs($this->recruiter);

    $this->post("{$this->base}/candidate-sources", [
        'source' => 'client_db',
        'file' => UploadedFile::fake()->createWithContent('list.csv', "foo,bar\n1,2"),
    ], ['Accept' => 'application/json'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('file');
});

it('in CV-only mode, ranks students, the client database and Naukri together, keeping the top N above the minimum score', function (): void {
    $student = shortlistStudent($this->tenant, ['python', 'sql', 'airflow', 'aws'], 80);
    $make = fn (string $source, string $name, array $skills, ?float $years, string $city, string $phone) => EmployerCandidate::create([
        'tenant_id' => $this->tenant->id,
        'employer_workspace_id' => $this->workspace->id,
        'source' => $source,
        'dedupe_key' => 'p:'.$phone,
        'name' => $name,
        'phone' => $phone,
        'current_title' => 'Data Engineer',
        'location' => $city,
        'experience_years' => $years,
        'skills' => $skills,
    ]);
    $strong = $make('client_db', 'Strong Client', ['Python', 'SQL', 'Airflow', 'AWS'], 4, 'Bengaluru', '9000000001');
    $naukri = $make('naukri', 'Naukri Match', ['python', 'sql', 'airflow'], 3, 'Bengaluru', '9000000002');
    $make('naukri', 'Weak Match', ['excel'], 1, 'Delhi', '9000000003'); // no required skill — never listed
    $make('client_db', 'Partial', ['python'], 0.5, 'Delhi', '9000000004'); // under the minimum score

    Sanctum::actingAs($this->recruiter);
    $this->putJson("{$this->base}/jobs/{$this->job->id}/shortlist/settings", [
        'top_n' => 3, 'min_score' => 50, 'sources' => ['browsejobs', 'client_db', 'naukri'], 'auto_on_publish' => true,
        'require_interview' => false,
    ])->assertOk();

    $response = $this->postJson("{$this->base}/jobs/{$this->job->id}/shortlist/run")->assertOk();

    $entries = collect($response->json('data.entries'));
    expect($entries)->toHaveCount(3)
        ->and($entries->pluck('source')->sort()->values()->all())->toBe(['browsejobs', 'client_db', 'naukri'])
        ->and($entries->pluck('rank')->all())->toBe([1, 2, 3])
        ->and($entries->firstWhere('employer_candidate_id', $strong->id)['match_score'])->toBe(100)
        ->and($entries->firstWhere('employer_candidate_id', $naukri->id)['reasons']['missing_skills'])->toBe(['aws'])
        ->and($response->json('data.last_run.considered.naukri.considered'))->toBe(2)
        ->and($response->json('data.last_run.shortlisted_count'))->toBe(3);

    // The client's own candidates show contact details; a student's stay withheld.
    expect($entries->firstWhere('employer_candidate_id', $strong->id)['phone'])->toBe('9000000001')
        ->and($entries->firstWhere('candidate_user_id', $student->id)['phone'])->toBeNull();
});

it('never re-adds someone the client removed, and searches only the sources switched on', function (): void {
    shortlistStudent($this->tenant, ['python', 'sql', 'airflow', 'aws']);
    $client = EmployerCandidate::create([
        'tenant_id' => $this->tenant->id, 'employer_workspace_id' => $this->workspace->id, 'source' => 'client_db',
        'dedupe_key' => 'p:9111111111', 'name' => 'Client Person', 'phone' => '9111111111',
        'skills' => ['python', 'sql', 'airflow', 'aws'], 'experience_years' => 3, 'location' => 'Bengaluru',
    ]);

    Sanctum::actingAs($this->recruiter);
    $this->putJson("{$this->base}/jobs/{$this->job->id}/shortlist/settings", [
        'top_n' => 10, 'min_score' => 40, 'sources' => ['browsejobs', 'client_db', 'naukri'], 'auto_on_publish' => true, 'require_interview' => false,
    ])->assertOk();
    $this->postJson("{$this->base}/jobs/{$this->job->id}/shortlist/run")->assertOk();
    $entry = JobShortlistEntry::query()->where('employer_candidate_id', $client->id)->firstOrFail();

    $this->postJson("{$this->base}/jobs/{$this->job->id}/shortlist/{$entry->id}/remove")
        ->assertOk()
        ->assertJsonPath('data.removed.0.employer_candidate_id', $client->id);

    $again = $this->postJson("{$this->base}/jobs/{$this->job->id}/shortlist/run")->assertOk();
    expect(collect($again->json('data.entries'))->pluck('employer_candidate_id')->filter()->all())->toBe([]);

    $this->putJson("{$this->base}/jobs/{$this->job->id}/shortlist/settings", [
        'top_n' => 10, 'min_score' => 0, 'sources' => ['client_db'], 'auto_on_publish' => true, 'require_interview' => false,
    ])->assertOk();
    $onlyClient = $this->postJson("{$this->base}/jobs/{$this->job->id}/shortlist/run")->assertOk();
    expect($onlyClient->json('data.entries'))->toBe([])
        ->and(array_keys($onlyClient->json('data.last_run.considered')))->toBe(['client_db']);
});

it('runs by itself when the JD is published, unless switched off', function (): void {
    Queue::fake();
    Sanctum::actingAs($this->recruiter);

    $draft = EmployerJob::factory()->for($this->tenant)->create([
        'employer_workspace_id' => $this->workspace->id,
        'created_by_id' => $this->recruiter->id,
    ]);
    $this->postJson("{$this->base}/jobs/{$draft->id}/publish")->assertOk();
    Queue::assertPushed(RunJobShortlistAgent::class, fn ($job) => $job->employerJobId === $draft->id);

    $manualOnly = EmployerJob::factory()->for($this->tenant)->create([
        'employer_workspace_id' => $this->workspace->id,
        'created_by_id' => $this->recruiter->id,
        'shortlist_settings' => ['top_n' => 5, 'min_score' => 40, 'sources' => ['browsejobs'], 'auto_on_publish' => false],
    ]);
    $this->postJson("{$this->base}/jobs/{$manualOnly->id}/publish")->assertOk();
    Queue::assertNotPushed(RunJobShortlistAgent::class, fn ($job) => $job->employerJobId === $manualOnly->id);
});

it('lets a hiring manager look but not import, change or run', function (): void {
    $manager = User::factory()->for($this->tenant)->create(['user_type' => 'employer']);
    EmployerMember::factory()->for($this->tenant)->hiringManager()->create([
        'employer_workspace_id' => $this->workspace->id,
        'user_id' => $manager->id,
    ]);
    Sanctum::actingAs($manager);

    $this->getJson("{$this->base}/jobs/{$this->job->id}/shortlist")->assertOk()->assertJsonPath('data.settings.top_n', 10);
    $this->postJson("{$this->base}/jobs/{$this->job->id}/shortlist/run")->assertForbidden();
    $this->putJson("{$this->base}/jobs/{$this->job->id}/shortlist/settings", [
        'top_n' => 5, 'min_score' => 10, 'sources' => [], 'auto_on_publish' => false,
    ])->assertForbidden();
    $this->post("{$this->base}/candidate-sources", ['source' => 'naukri', 'file' => naukriCsv([])], ['Accept' => 'application/json'])
        ->assertForbidden();
});

it('pulls students who have not taken the AI Readiness Interview yet, unless told not to', function (): void {
    $student = shortlistStudent($this->tenant, ['python', 'sql', 'airflow', 'aws'], 60);
    CvProfile::query()->where('user_id', $student->id)->update(['cv_mock_completed_at' => null]);
    Sanctum::actingAs($this->recruiter);
    $this->putJson("{$this->base}/jobs/{$this->job->id}/shortlist/settings", [
        'top_n' => 10, 'min_score' => 40, 'sources' => ['browsejobs'], 'auto_on_publish' => true, 'require_interview' => false,
    ])->assertOk();

    $run = $this->postJson("{$this->base}/jobs/{$this->job->id}/shortlist/run")->assertOk();
    $entry = collect($run->json('data.entries'))->firstWhere('candidate_user_id', $student->id);
    expect($entry)->not->toBeNull()
        ->and($entry['reasons']['ai_readiness'])->toBeNull()
        ->and($run->json('data.settings.students_need_readiness'))->toBeFalse()
        ->and($run->json('data.settings.require_interview'))->toBeFalse();

    $this->putJson("{$this->base}/jobs/{$this->job->id}/shortlist/settings", [
        'top_n' => 10, 'min_score' => 0, 'sources' => ['browsejobs'], 'auto_on_publish' => true, 'students_need_readiness' => true,
        'require_interview' => false,
    ])->assertOk();
    $strict = $this->postJson("{$this->base}/jobs/{$this->job->id}/shortlist/run")->assertOk();
    expect($strict->json('data.entries'))->toBe([]);
});

it('keeps the closest few when nobody clears the minimum match', function (): void {
    $student = shortlistStudent($this->tenant, ['python'], 10);
    Sanctum::actingAs($this->recruiter);
    $this->putJson("{$this->base}/jobs/{$this->job->id}/shortlist/settings", [
        'top_n' => 10, 'min_score' => 95, 'sources' => ['browsejobs'], 'auto_on_publish' => true, 'require_interview' => false,
    ])->assertOk();

    $this->postJson("{$this->base}/jobs/{$this->job->id}/shortlist/run")->assertOk()->assertJsonPath('data.entries', []);

    $run = JobShortlistRun::query()->latest('id')->firstOrFail();
    expect($run->near_misses[0]['name'])->toBe($student->name)
        ->and($run->near_misses[0]['matched_skills'])->toBe(['python'])
        ->and(app(ShortlistReport::class)->text($this->job))
        ->toContain('Nobody reached your minimum match of 95%')->toContain($student->name);
});

it('validates the settings', function (): void {
    Sanctum::actingAs($this->recruiter);

    $this->putJson("{$this->base}/jobs/{$this->job->id}/shortlist/settings", [
        'top_n' => 0, 'min_score' => 150, 'sources' => ['linkedin'], 'auto_on_publish' => 'maybe',
    ])->assertUnprocessable()->assertJsonValidationErrors(['top_n', 'min_score', 'sources.0', 'auto_on_publish']);
});

it('keeps one company\'s candidates and shortlist away from everyone else', function (): void {
    EmployerCandidate::create([
        'tenant_id' => $this->tenant->id, 'employer_workspace_id' => $this->workspace->id, 'source' => 'naukri',
        'dedupe_key' => 'p:9222222222', 'name' => 'Private Person', 'phone' => '9222222222', 'skills' => ['python'],
    ]);

    // A member of another workspace in the same tenant: 403.
    $stranger = User::factory()->for($this->tenant)->create(['user_type' => 'employer']);
    $otherWorkspace = EmployerWorkspace::factory()->for($this->tenant)->create();
    EmployerMember::factory()->for($this->tenant)->create(['employer_workspace_id' => $otherWorkspace->id, 'user_id' => $stranger->id]);
    Sanctum::actingAs($stranger);
    $this->getJson("{$this->base}/candidate-sources")->assertForbidden();
    $this->getJson("{$this->base}/jobs/{$this->job->id}/shortlist")->assertForbidden();

    // Someone from a different tenant cannot even resolve the workspace: 404.
    $otherTenant = Tenant::factory()->create();
    $outsider = User::factory()->for($otherTenant)->create(['user_type' => 'employer']);
    Sanctum::actingAs($outsider);
    $this->getJson("{$this->base}/candidate-sources")->assertNotFound();
    $this->postJson("{$this->base}/jobs/{$this->job->id}/shortlist/run")->assertNotFound();

    // And another workspace's agent never sees this workspace's candidates.
    $otherJob = EmployerJob::factory()->for($this->tenant)->published()->create([
        'employer_workspace_id' => $otherWorkspace->id,
        'created_by_id' => $stranger->id,
        'skills' => ['python'],
    ]);
    Sanctum::actingAs($stranger);
    $this->putJson("/api/v1/employer/workspaces/{$otherWorkspace->id}/jobs/{$otherJob->id}/shortlist/settings", [
        'top_n' => 10, 'min_score' => 0, 'sources' => ['naukri'], 'auto_on_publish' => true,
    ])->assertOk();
    $run = $this->postJson("/api/v1/employer/workspaces/{$otherWorkspace->id}/jobs/{$otherJob->id}/shortlist/run")->assertOk();
    expect($run->json('data.entries'))->toBe([])
        ->and($run->json('data.last_run.considered.naukri.considered'))->toBe(0);
});

/** A finished AI interview for a role, as the voice room leaves it. */
function finishedInterview(User $student, string $role, int $score): MockInterview
{
    $blueprint = MockBlueprint::query()->create([
        'tenant_id' => $student->tenant_id, 'role_title' => $role, 'competencies' => ['x'], 'opening_question' => 'Hi', 'is_active' => true,
    ]);

    return MockInterview::query()->create([
        'tenant_id' => $student->tenant_id, 'user_id' => $student->id, 'mock_blueprint_id' => $blueprint->id,
        'mode' => 'text', 'status' => 'completed', 'overall_score' => $score, 'started_at' => now(), 'completed_at' => now(),
    ]);
}

function screeningSetup($test): FakeWhatsAppClient
{
    config(['app.frontend_url' => 'https://browsejobs.ai']);
    Cache::put('interview_template_statuses', [InterviewTemplates::INVITE => 'APPROVED', InterviewTemplates::LINK => 'APPROVED'], 600);
    JdMock::factory()->for($test->tenant)->ready()->create(['employer_job_id' => $test->job->id]);
    $wa = new FakeWhatsAppClient;
    app()->instance(WhatsAppClient::class, $wa);

    return $wa;
}

it('with include_cv_only off: CV 60 + interview 65 first, CV 45 + interview 45 next, and a good CV with no interview is screened', function (): void {
    $wa = screeningSetup($this);
    $this->job->update(['shortlist_settings' => ['include_cv_only' => false]]);
    $all = ['python', 'sql', 'airflow', 'aws'];
    $phone = fn (User $u, string $p) => tap($u)->update(['phone' => $p]);

    $top = $phone(shortlistStudent($this->tenant, $all), '9000000011');
    finishedInterview($top, 'Data Engineer', 80);
    $next = $phone(shortlistStudent($this->tenant, $all), '9000000012');
    finishedInterview($next, 'Senior Data Engineer', 55);                           // 55 < 65 → second priority
    $cvOnly = $phone(shortlistStudent($this->tenant, $all), '9000000013');      // no interview → screened
    $weakCv = $phone(shortlistStudent($this->tenant, ['python']), '9000000014'); // CV 25% → out, however good the interview
    finishedInterview($weakCv, 'Data Engineer', 95);
    $weakInterview = $phone(shortlistStudent($this->tenant, $all), '9000000015'); // interview 40 → out, not re-screened
    finishedInterview($weakInterview, 'Data Engineer', 40);
    $otherRole = $phone(shortlistStudent($this->tenant, $all), '9000000016');   // a Data Analyst interview does not count
    finishedInterview($otherRole, 'Data Analyst', 90);

    Sanctum::actingAs($this->recruiter);
    $data = $this->postJson("{$this->base}/jobs/{$this->job->id}/shortlist/run")->assertOk()->json('data');

    $entries = collect($data['entries']);
    expect($entries->pluck('candidate_user_id')->all())->toBe([$top->id, $next->id])
        ->and($entries->pluck('reasons.tier')->all())->toBe([1, 2])
        ->and($entries[0]['reasons']['cv_match'])->toBe(100)
        ->and($entries[0]['reasons']['interview_score'])->toBe(80)
        ->and($entries[0]['match_score'])->toBe(90)
        ->and($entries[1]['match_score'])->toBe(78)
        ->and($data['settings'])->toMatchArray([
            'require_interview' => true, 'first_cv_bar' => 60, 'first_interview_bar' => 65,
            'second_cv_bar' => 45, 'second_interview_bar' => 45, 'auto_invite' => true,
        ]);

    // A good CV with no AI interview for the role gets the AI screening interview, not a place on the list.
    $screened = collect($data['awaiting'])->pluck('name')->all();
    expect($screened)->toContain($cvOnly->name)->toContain($otherRole->name)
        ->and($screened)->not->toContain($weakCv->name)->not->toContain($weakInterview->name);
    $invite = InterviewInvite::query()->where('candidate_user_id', $cvOnly->id)->firstOrFail();
    $link = collect($wa->sent)->first(fn ($m) => $m['template'] === InterviewTemplates::LINK && $m['to'] === '919000000013');
    expect($invite->round)->toBe('screen')
        ->and($invite->status)->toBe('open')
        ->and($link['parameters'])->toBe([$invite->firstName(), 'AI screening', 'Data Engineer'])
        ->and(EmployerJobApplication::query()->where('candidate_id', $cvOnly->id)->first()->stage->value)->toBe('applied');

    // Everyone shortlisted is invited to L1 straight away; nobody else is.
    $l1 = InterviewInvite::query()->where('round', 'l1')->pluck('candidate_user_id')->sort()->values()->all();
    expect($l1)->toBe(collect([$top->id, $next->id])->sort()->values()->all())
        ->and(collect($wa->sent)->where('template', InterviewTemplates::INVITE)->count())->toBe(2);

    // Running again never messages anyone twice.
    $count = count($wa->sent);
    $this->postJson("{$this->base}/jobs/{$this->job->id}/shortlist/run")->assertOk();
    expect($wa->sent)->toHaveCount($count);
});

it('with include_cv_only off, shortlists someone once they pass the AI screening interview, then invites them to L1', function (): void {
    $wa = screeningSetup($this);
    $this->job->update(['shortlist_settings' => ['include_cv_only' => false]]);
    $student = tap(shortlistStudent($this->tenant, ['python', 'sql', 'airflow', 'aws']))->update(['phone' => '9000000021']);

    Sanctum::actingAs($this->recruiter);
    $this->postJson("{$this->base}/jobs/{$this->job->id}/shortlist/run")->assertOk()->assertJsonPath('data.entries', []);
    $invite = InterviewInvite::query()->where('round', 'screen')->firstOrFail();

    // The link page names the round; someone else's login cannot use it.
    expect($this->getJson("/api/v1/interview-invites/{$invite->token}")->json('data.round'))->toBe('AI screening');
    Sanctum::actingAs(User::factory()->for($this->tenant)->create(['user_type' => 'student']));
    $this->postJson("/api/v1/me/interview-invites/{$invite->token}/start")->assertUnprocessable();

    // Start opens the JD's own interview.
    Sanctum::actingAs($student);
    $start = $this->postJson("/api/v1/me/interview-invites/{$invite->token}/start")->assertOk()->json('data');
    $mock = MockInterview::query()->with('blueprint')->findOrFail($start['mock_id']);
    expect($mock->blueprint->employer_job_id)->toBe($this->job->id)
        ->and($start['room_url'])->toBe("/job-interview/{$mock->id}/room");

    // They finish with 78: the agent runs again and puts them in the first group.
    $mock->update(['status' => MockInterview::STATUS_COMPLETED, 'overall_score' => 78, 'completed_at' => now()]);
    MockCompleted::dispatch($student, $mock->fresh());

    $entry = JobShortlistEntry::query()->where('candidate_user_id', $student->id)->firstOrFail();
    expect($invite->fresh()->status)->toBe('done')
        ->and($entry->reasons['tier'])->toBe(1)
        ->and($entry->reasons['interview_score'])->toBe(78)
        ->and(JobShortlistRun::query()->latest('id')->value('trigger'))->toBe('screening')
        ->and(InterviewInvite::query()->where('round', 'l1')->where('candidate_user_id', $student->id)->value('status'))->toBe('sent')
        ->and(collect($wa->sent)->last()['template'])->toBe(InterviewTemplates::INVITE);

    // The finished screening link cannot be used again.
    $this->postJson("/api/v1/me/interview-invites/{$invite->token}/start")->assertUnprocessable();
});

it('with include_cv_only off, waits for Meta before sending the AI screening interview and does not shortlist on the CV alone', function (): void {
    $this->job->update(['shortlist_settings' => ['include_cv_only' => false]]);
    Cache::put('interview_template_statuses', [InterviewTemplates::INVITE => null, InterviewTemplates::LINK => null], 600);
    shortlistStudent($this->tenant, ['python', 'sql', 'airflow', 'aws']);

    Sanctum::actingAs($this->recruiter);
    $data = $this->postJson("{$this->base}/jobs/{$this->job->id}/shortlist/run")->assertOk()->json('data');

    expect($data['entries'])->toBe([])
        ->and($data['awaiting'][0]['invite_status'])->toBeNull()
        ->and(InterviewInvite::query()->count())->toBe(0)
        ->and(app(ShortlistReport::class)->text($this->job))->toContain('Taking the AI interview')->toContain('invite not sent yet');
});

it('keeps the second-priority bars at or below the first', function (): void {
    Sanctum::actingAs($this->recruiter);

    $this->putJson("{$this->base}/jobs/{$this->job->id}/shortlist/settings", [
        'top_n' => 10, 'min_score' => 40, 'sources' => ['browsejobs'], 'auto_on_publish' => true,
        'first_cv_bar' => 60, 'second_cv_bar' => 75, 'first_interview_bar' => 65, 'second_interview_bar' => 70,
    ])->assertUnprocessable()->assertJsonValidationErrors(['second_cv_bar', 'second_interview_bar']);
});

it('in test mode considers only the workspace test students and messages nobody else', function (): void {
    $wa = screeningSetup($this);
    $all = ['python', 'sql', 'airflow', 'aws'];
    $tester = tap(shortlistStudent($this->tenant, $all))->update(['phone' => '9000000031']);
    finishedInterview($tester, 'Data Engineer', 70);
    $other = tap(shortlistStudent($this->tenant, $all))->update(['phone' => '9000000032']);
    finishedInterview($other, 'Data Engineer', 90);
    $cvOnly = tap(shortlistStudent($this->tenant, $all))->update(['phone' => '9000000033']); // would be screened normally
    EmployerCandidate::create([
        'tenant_id' => $this->tenant->id, 'employer_workspace_id' => $this->workspace->id, 'source' => 'naukri',
        'dedupe_key' => 'p:9000000034', 'name' => 'Naukri Person', 'phone' => '9000000034', 'skills' => $all,
    ]);
    $this->workspace->update(['shortlist_test_user_ids' => [$tester->id]]);

    Sanctum::actingAs($this->recruiter);
    $data = $this->postJson("{$this->base}/jobs/{$this->job->id}/shortlist/run")->assertOk()->json('data');

    expect(collect($data['entries'])->pluck('candidate_user_id')->all())->toBe([$tester->id])
        ->and($data['awaiting'])->toBe([])
        ->and($data['test_mode'])->toBeTrue()
        ->and(array_keys($data['last_run']['considered']))->toBe(['browsejobs'])
        ->and(collect($wa->sent)->pluck('to')->unique()->values()->all())->toBe(['919000000031'])
        ->and(InterviewInvite::query()->whereIn('candidate_user_id', [$other->id, $cvOnly->id])->exists())->toBeFalse();

    // Test mode off: everyone is considered again.
    $this->workspace->update(['shortlist_test_user_ids' => null]);
    $again = $this->postJson("{$this->base}/jobs/{$this->job->id}/shortlist/run")->assertOk()->json('data');
    expect(collect($again['entries'])->pluck('candidate_user_id')->all())->toContain($other->id)
        ->and($again['test_mode'])->toBeFalse();
});
