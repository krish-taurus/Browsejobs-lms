<?php

declare(strict_types=1);

use App\Models\EmployerJob;
use App\Models\EmployerWorkspace;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\artisan;

beforeEach(function (): void {
    Storage::fake('public');
    $this->tenant = Tenant::factory()->domain('logo-board.test')->create();
    $this->workspace = EmployerWorkspace::factory()->for($this->tenant)->create(['name' => 'Acme Technologies']);
});

function logoFile(string $name = 'logo.png'): string
{
    // A fake upload's temp file vanishes with the object — keep a copy.
    $fake = UploadedFile::fake()->image($name, 96, 96);
    $path = sys_get_temp_dir().'/'.uniqid('logo-', true).'-'.$name;
    copy($fake->getRealPath(), $path);

    return $path;
}

it('stores a logo on the public disk and points the workspace at it', function (): void {
    artisan('employer:set-logo', ['workspace' => $this->workspace->id, 'path' => logoFile()])->assertSuccessful();

    $path = $this->workspace->refresh()->logo_path;
    expect($path)->toStartWith('employer-logos/'.$this->workspace->id.'-')->toEndWith('.png');
    Storage::disk('public')->assertExists($path);
    expect($this->workspace->logoUrl())->toContain('/storage/employer-logos/');
});

it('replaces the previous logo file instead of leaving it behind', function (): void {
    artisan('employer:set-logo', ['workspace' => $this->workspace->id, 'path' => logoFile()])->assertSuccessful();
    $first = $this->workspace->refresh()->logo_path;

    artisan('employer:set-logo', ['workspace' => $this->workspace->id, 'path' => logoFile('new.jpg')])->assertSuccessful();
    $second = $this->workspace->refresh()->logo_path;

    expect($second)->not->toBe($first)->toEndWith('.jpg');
    Storage::disk('public')->assertMissing($first);
    Storage::disk('public')->assertExists($second);
});

it('refuses files that are not PNG, JPEG or WebP images', function (): void {
    $text = tempnam(sys_get_temp_dir(), 'logo');
    file_put_contents($text, 'not an image');
    artisan('employer:set-logo', ['workspace' => $this->workspace->id, 'path' => $text])->assertFailed();

    $svg = tempnam(sys_get_temp_dir(), 'logo');
    file_put_contents($svg, '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');
    artisan('employer:set-logo', ['workspace' => $this->workspace->id, 'path' => $svg])->assertFailed();

    expect($this->workspace->refresh()->logo_path)->toBeNull();
});

it('removes a logo and its file', function (): void {
    artisan('employer:set-logo', ['workspace' => $this->workspace->id, 'path' => logoFile()])->assertSuccessful();
    $path = $this->workspace->refresh()->logo_path;

    artisan('employer:set-logo', ['workspace' => $this->workspace->id, '--remove' => true])->assertSuccessful();

    expect($this->workspace->refresh()->logo_path)->toBeNull();
    Storage::disk('public')->assertMissing($path);
});

it('fails cleanly for an unknown workspace', function (): void {
    artisan('employer:set-logo', ['workspace' => 999999, 'path' => logoFile()])->assertFailed();
});

it('sends the company logo with direct-hiring jobs, and null without one', function (): void {
    EmployerJob::factory()->for($this->tenant)->published()->create([
        'employer_workspace_id' => $this->workspace->id,
        'title' => 'Data Engineer',
    ]);
    $candidate = User::factory()->for($this->tenant)->create(['user_type' => 'student']);
    Sanctum::actingAs($candidate);

    $this->getJson('http://logo-board.test/api/v1/job-board')
        ->assertOk()
        ->assertJsonPath('data.internal.0.company_logo', null);

    artisan('employer:set-logo', ['workspace' => $this->workspace->id, 'path' => logoFile()])->assertSuccessful();

    $logo = $this->getJson('http://logo-board.test/api/v1/job-board')->assertOk()->json('data.internal.0.company_logo');
    expect($logo)->toContain('/storage/employer-logos/'.$this->workspace->id.'-');
});
