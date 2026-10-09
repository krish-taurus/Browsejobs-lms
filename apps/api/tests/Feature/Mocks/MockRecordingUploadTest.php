<?php

declare(strict_types=1);

use App\Models\Course;
use App\Models\MockBlueprint;
use App\Models\MockInterview;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\post;

beforeEach(function (): void {
    Storage::fake('s3');
    $this->tenant = Tenant::factory()->create();
    $this->student = User::factory()->for($this->tenant)->create(['user_type' => 'student']);
    $blueprint = MockBlueprint::factory()->for($this->tenant)->create(['course_id' => Course::factory()->for($this->tenant)->create()->id]);
    $this->mock = MockInterview::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->student->id,
        'mock_blueprint_id' => $blueprint->id,
        'mode' => 'text',
        'is_room' => true,
        'status' => MockInterview::STATUS_IN_PROGRESS,
        'started_at' => now(),
    ]);
    // Real bytes, so stored sizes can be compared (a plain fake upload only
    // reports a size and is empty). Size-limit checks use a reported size.
    $this->upload = fn (int $kilobytes, bool $real = true) => post(
        "/api/v1/me/mocks/{$this->mock->id}/recording",
        ['recording' => $real
            ? UploadedFile::fake()->createWithContent('interview.webm', str_repeat('x', $kilobytes * 1024))
            : UploadedFile::fake()->create('interview.webm', $kilobytes, 'video/webm')],
        ['Accept' => 'application/json'],
    );
});

it('saves a recording while the interview is still being graded', function (): void {
    Sanctum::actingAs($this->student);

    ($this->upload)(3000)->assertOk();

    $path = $this->mock->refresh()->recording_url;
    expect($path)->toBe("mock-recordings/{$this->tenant->id}/{$this->mock->id}.webm");
    expect(Storage::disk('s3')->size($path))->toBe(3000 * 1024);
});

it('never lets a shorter clip replace a longer recording already saved', function (): void {
    Sanctum::actingAs($this->student);
    ($this->upload)(3000)->assertOk();
    $path = $this->mock->refresh()->recording_url;

    // e.g. the room was reloaded and only the last few seconds were recorded
    ($this->upload)(200)->assertOk()->assertJsonPath('kept', 'existing');

    expect($this->mock->refresh()->recording_url)->toBe($path);
    expect(Storage::disk('s3')->size($path))->toBe(3000 * 1024);
    expect(Storage::disk('s3')->files("mock-recordings/{$this->tenant->id}"))->toHaveCount(2);

    // A longer one does replace it.
    ($this->upload)(5000)->assertOk();
    expect(Storage::disk('s3')->size($this->mock->refresh()->recording_url))->toBe(5000 * 1024);
});

it('accepts a long interview recording up to 95 MB and refuses larger', function (): void {
    Sanctum::actingAs($this->student);

    ($this->upload)(90 * 1024, false)->assertOk();
    ($this->upload)(96 * 1024, false)->assertUnprocessable();
});

it('refuses a recording for someone else\'s interview', function (): void {
    Sanctum::actingAs(User::factory()->for($this->tenant)->create(['user_type' => 'student']));

    ($this->upload)(100)->assertNotFound();
    expect($this->mock->refresh()->recording_url)->toBeNull();
});
