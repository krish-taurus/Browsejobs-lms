<?php

declare(strict_types=1);

use App\Enums\EmployerJobStatus;
use App\Models\EmployerJob;
use App\Models\EmployerWorkspace;
use App\Models\Tenant;
use App\Models\User;

/**
 * PRD-E F10 — one published role, readable without an account.
 *
 * A job seeker should be able to read the description before handing over an
 * email address. The risk in opening it up is that a public route quietly
 * becomes the looser one, so these pin the disclosure rules to the same
 * resource the signed-in view uses.
 */
beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create(['slug' => 'browsejobs']);
    $this->workspace = EmployerWorkspace::factory()->for($this->tenant)->create(['name' => 'Acme Technologies']);
    $this->recruiter = User::factory()->for($this->tenant)->create(['user_type' => 'employer']);
});

function publishedJob(mixed $test, array $attributes = []): EmployerJob
{
    return EmployerJob::factory()->for($test->tenant)->published()->create(array_merge([
        'employer_workspace_id' => $test->workspace->id,
        'created_by_id' => $test->recruiter->id,
        'title' => 'Data Engineer',
        'description' => 'Own our lakehouse pipelines end to end.',
    ], $attributes));
}

it('serves a published role to a signed-out visitor', function (): void {
    $job = publishedJob($this);

    $this->getJson("/api/v1/job-board/{$job->id}")
        ->assertOk()
        ->assertJsonPath('data.title', 'Data Engineer')
        ->assertJsonPath('data.description', 'Own our lakehouse pipelines end to end.')
        ->assertJsonPath('data.company.name', 'Acme Technologies');
});

it('hides a role the employer has not published', function (): void {
    foreach ([EmployerJobStatus::Draft, EmployerJobStatus::Paused, EmployerJobStatus::Closed] as $status) {
        $job = publishedJob($this);
        $job->forceFill(['status' => $status->value])->save();

        // A draft is an employer's private working copy, and a closed role is
        // a dead end. Neither belongs on a public URL someone could share.
        $this->getJson("/api/v1/job-board/{$job->id}")->assertNotFound();
    }
});

it('withholds pay when the employer did not opt in to showing it', function (): void {
    $hidden = publishedJob($this, [
        'ctc_visible' => false,
        'ctc_min_paise' => 120000000,
        'ctc_max_paise' => 180000000,
    ]);

    $body = $this->getJson("/api/v1/job-board/{$hidden->id}")->assertOk();

    expect($body->json('data'))->not->toHaveKey('ctc_min_paise')
        ->and($body->getContent())->not->toContain('120000000');

    $shown = publishedJob($this, [
        'ctc_visible' => true,
        'ctc_min_paise' => 120000000,
        'ctc_max_paise' => 180000000,
    ]);

    $this->getJson("/api/v1/job-board/{$shown->id}")
        ->assertOk()
        ->assertJsonPath('data.ctc_min_paise', 120000000);
});

it('keeps one tenant role off another tenant board', function (): void {
    $other = Tenant::factory()->create(['domain' => 'other.test']);
    $otherWorkspace = EmployerWorkspace::factory()->for($other)->create();
    $foreign = EmployerJob::factory()->for($other)->published()->create([
        'employer_workspace_id' => $otherWorkspace->id,
        'created_by_id' => User::factory()->for($other)->create(['user_type' => 'employer'])->id,
    ]);

    // Resolved by host: a visitor on this tenant's domain must not be able to
    // read another tenant's posting by guessing an id.
    $this->getJson("/api/v1/job-board/{$foreign->id}")->assertNotFound();
});
