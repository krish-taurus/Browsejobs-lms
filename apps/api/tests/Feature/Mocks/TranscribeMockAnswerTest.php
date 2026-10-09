<?php

declare(strict_types=1);

use App\Models\Course;
use App\Models\MockBlueprint;
use App\Models\MockInterview;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\post;

beforeEach(function () {
    config(['services.elevenlabs.api_key' => 'test-key']);
    $this->tenant = Tenant::factory()->create();
    $this->student = User::factory()->for($this->tenant)->create(['user_type' => 'student']);
    $course = Course::factory()->for($this->tenant)->create();
    $blueprint = MockBlueprint::factory()->for($this->tenant)->create(['course_id' => $course->id]);
    $this->mock = MockInterview::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->student->id,
        'mock_blueprint_id' => $blueprint->id,
        'mode' => 'text',
        'is_room' => true,
        'status' => MockInterview::STATUS_IN_PROGRESS,
        'started_at' => now(),
    ]);
});

function answerClip(): UploadedFile
{
    // A tiny but real Ogg/Opus header so the server's MIME sniffing sees audio.
    return UploadedFile::fake()->createWithContent('answer.ogg', "OggS\x00\x02".str_repeat("\x00", 64).'OpusHead'.str_repeat("\x01", 64));
}

it('transcribes a spoken answer through ElevenLabs without exposing the key', function () {
    Http::fake(['api.elevenlabs.io/v1/speech-to-text' => Http::response(['text' => ' I build pipelines in Spark. '])]);
    Sanctum::actingAs($this->student);

    post("/api/v1/me/mocks/{$this->mock->id}/transcribe", ['audio' => answerClip()], ['Accept' => 'application/json'])
        ->assertOk()
        ->assertExactJson(['data' => ['text' => 'I build pipelines in Spark.']]);

    Http::assertSent(fn (HttpRequest $r) => $r->url() === 'https://api.elevenlabs.io/v1/speech-to-text'
        && $r->hasHeader('xi-api-key', 'test-key'));
});

it('offers typing when no speech key is configured or ElevenLabs fails', function () {
    Sanctum::actingAs($this->student);

    config(['services.elevenlabs.api_key' => '']);
    post("/api/v1/me/mocks/{$this->mock->id}/transcribe", ['audio' => answerClip()], ['Accept' => 'application/json'])
        ->assertStatus(503)->assertJsonPath('error.code', 'stt_unavailable');

    config(['services.elevenlabs.api_key' => 'test-key']);
    Http::fake(['api.elevenlabs.io/*' => Http::response(['detail' => 'quota'], 401)]);
    post("/api/v1/me/mocks/{$this->mock->id}/transcribe", ['audio' => answerClip()], ['Accept' => 'application/json'])
        ->assertStatus(503)->assertJsonPath('error.code', 'stt_unavailable');
});

it('requires an audio clip', function () {
    Http::fake();
    Sanctum::actingAs($this->student);

    post("/api/v1/me/mocks/{$this->mock->id}/transcribe", [], ['Accept' => 'application/json'])->assertUnprocessable();
    Http::assertNothingSent();
});

it('refuses an interview that has already ended', function () {
    Http::fake();
    $this->mock->forceFill(['status' => MockInterview::STATUS_COMPLETED])->save();
    Sanctum::actingAs($this->student);

    post("/api/v1/me/mocks/{$this->mock->id}/transcribe", ['audio' => answerClip()], ['Accept' => 'application/json'])->assertStatus(409);
    Http::assertNothingSent();
});

it('never transcribes into someone else\'s interview, in this tenant or another', function () {
    Http::fake();

    $classmate = User::factory()->for($this->tenant)->create(['user_type' => 'student']);
    Sanctum::actingAs($classmate);
    post("/api/v1/me/mocks/{$this->mock->id}/transcribe", ['audio' => answerClip()], ['Accept' => 'application/json'])->assertNotFound();

    $outsider = User::factory()->for(Tenant::factory()->create())->create(['user_type' => 'student']);
    Sanctum::actingAs($outsider);
    post("/api/v1/me/mocks/{$this->mock->id}/transcribe", ['audio' => answerClip()], ['Accept' => 'application/json'])->assertNotFound();

    Http::assertNothingSent();
});
