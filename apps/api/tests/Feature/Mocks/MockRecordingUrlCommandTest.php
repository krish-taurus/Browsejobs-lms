<?php

declare(strict_types=1);

use App\Models\Course;
use App\Models\MockBlueprint;
use App\Models\MockInterview;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\artisan;

beforeEach(function (): void {
    Storage::fake('s3');
    Storage::disk('s3')->buildTemporaryUrlsUsing(
        fn (string $path, $expiration) => 'https://signed.test/'.$path.'?expires='.$expiration->getTimestamp(),
    );

    $tenant = Tenant::factory()->create();
    $student = User::factory()->for($tenant)->create(['user_type' => 'student']);
    $blueprint = MockBlueprint::factory()->for($tenant)->create(['course_id' => Course::factory()->for($tenant)->create()->id]);

    $this->make = fn (?string $recording) => MockInterview::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $student->id,
        'mock_blueprint_id' => $blueprint->id,
        'mode' => 'text',
        'is_room' => true,
        'status' => MockInterview::STATUS_COMPLETED,
        'recording_url' => $recording,
        'started_at' => now()->subHour(),
        'completed_at' => now(),
    ]);
});

it('prints a temporary link to a stored recording', function (): void {
    $mock = ($this->make)('mock-recordings/1/132.webm');

    artisan('mock:recording-url', ['mock' => $mock->id])
        ->expectsOutputToContain('https://signed.test/mock-recordings/1/132.webm?expires=')
        ->assertSuccessful();
});

it('passes a full recording URL straight through', function (): void {
    $mock = ($this->make)('https://cdn.example.com/rec.webm');

    artisan('mock:recording-url', ['mock' => $mock->id])
        ->expectsOutput('https://cdn.example.com/rec.webm')
        ->assertSuccessful();
});

it('fails when there is no recording or no such interview', function (): void {
    $mock = ($this->make)(null);

    artisan('mock:recording-url', ['mock' => $mock->id])->assertFailed();
    artisan('mock:recording-url', ['mock' => 999999])->assertFailed();
});
