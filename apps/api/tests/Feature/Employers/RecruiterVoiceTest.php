<?php

declare(strict_types=1);

use App\Models\EmployerMember;
use App\Models\EmployerWorkspace;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;

/** A small but real-looking recording. */
function voiceClip(): UploadedFile
{
    return UploadedFile::fake()->createWithContent('command.ogg', "OggS\x00\x02".str_repeat("\x00", 64).'OpusHead'.str_repeat("\x01", 400));
}

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    $this->recruiter = User::factory()->for($this->tenant)->create(['user_type' => 'employer']);
    $this->workspace = EmployerWorkspace::factory()->for($this->tenant)->create();
    EmployerMember::factory()->for($this->tenant)->create(['employer_workspace_id' => $this->workspace->id, 'user_id' => $this->recruiter->id]);
    $this->url = "/api/v1/employer/workspaces/{$this->workspace->id}/agent/transcribe";
    config(['services.elevenlabs.api_key' => 'test-key']);
});

it('turns a recorded voice command into text for browsers with no speech recognition', function (): void {
    Http::fake(['api.elevenlabs.io/*' => Http::response(['text' => ' send the L1 invites '])]);
    Sanctum::actingAs($this->recruiter);

    $this->post($this->url, ['audio' => voiceClip()], ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonPath('data.text', 'send the L1 invites');

    // No language is forced: the recruiter may speak English, Hindi or a mix.
    Http::assertSent(fn ($request) => str_contains($request->url(), 'speech-to-text') && ! str_contains((string) $request->body(), 'language_code'));
});

it('refuses anything that is not audio, and a request with no file', function (): void {
    Http::fake();
    Sanctum::actingAs($this->recruiter);

    $this->post($this->url, ['audio' => UploadedFile::fake()->create('notes.pdf', 20, 'application/pdf')], ['Accept' => 'application/json'])
        ->assertUnprocessable();
    $this->post($this->url, [], ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonValidationErrors('audio');
    // An empty recording is refused here, not sent upstream to fail.
    $this->post($this->url, ['audio' => UploadedFile::fake()->create('command.webm', 0, 'audio/webm')], ['Accept' => 'application/json'])
        ->assertUnprocessable();
    Http::assertNothingSent();
});

it('says so plainly when voice is not set up or the service fails', function (): void {
    Sanctum::actingAs($this->recruiter);
    $upload = fn () => ['audio' => voiceClip()];

    config(['services.elevenlabs.api_key' => '']);
    $this->post($this->url, $upload(), ['Accept' => 'application/json'])->assertStatus(503)->assertJsonPath('error.code', 'stt_unavailable');

    config(['services.elevenlabs.api_key' => 'test-key']);
    Http::fake(['api.elevenlabs.io/*' => Http::response(['detail' => 'quota'], 429)]);
    $this->post($this->url, $upload(), ['Accept' => 'application/json'])->assertStatus(503);
});

it('keeps voice commands to members of the workspace', function (): void {
    Http::fake(['api.elevenlabs.io/*' => Http::response(['text' => 'hello'])]);
    $upload = fn () => ['audio' => voiceClip()];

    // Someone from another company in the same tenant.
    $stranger = User::factory()->for($this->tenant)->create(['user_type' => 'employer']);
    $other = EmployerWorkspace::factory()->for($this->tenant)->create();
    EmployerMember::factory()->for($this->tenant)->create(['employer_workspace_id' => $other->id, 'user_id' => $stranger->id]);
    Sanctum::actingAs($stranger);
    $this->post($this->url, $upload(), ['Accept' => 'application/json'])->assertForbidden();

    // Another tenant cannot even find the workspace.
    Sanctum::actingAs(User::factory()->for(Tenant::factory()->create())->create(['user_type' => 'employer']));
    $this->post($this->url, $upload(), ['Accept' => 'application/json'])->assertNotFound();
    Http::assertNothingSent();
});
