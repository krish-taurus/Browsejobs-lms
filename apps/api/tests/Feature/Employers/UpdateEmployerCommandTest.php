<?php

declare(strict_types=1);

use App\Models\EmployerWorkspace;
use App\Models\Tenant;

use function Pest\Laravel\artisan;

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    $this->workspace = EmployerWorkspace::factory()->for($this->tenant)->create([
        'name' => 'Acme Technologies',
        'slug' => 'acme-technologies',
        'website' => 'https://acme.com',
        'industry' => 'IT Services',
        'company_size' => '11-50',
        'gstin' => '29ABCDE1234F1Z5',
        'locations' => ['Hyderabad', 'Pune'],
        'social_links' => ['linkedin' => 'https://linkedin.com/company/acme', 'twitter' => 'https://x.com/acme'],
    ]);
});

it('changes only the fields it is given and keeps the slug', function (): void {
    artisan('employer:update', [
        'workspace' => $this->workspace->id,
        '--name' => 'Acme Labs',
        '--city' => 'Bengaluru',
        '--size' => '51-200',
        '--instagram' => 'https://instagram.com/acme',
    ])->assertSuccessful();

    $w = $this->workspace->refresh();
    expect($w->name)->toBe('Acme Labs')
        ->and($w->slug)->toBe('acme-technologies')
        ->and($w->company_size)->toBe('51-200')
        ->and($w->locations)->toBe(['Bengaluru', 'Pune'])
        ->and($w->website)->toBe('https://acme.com')
        ->and($w->industry)->toBe('IT Services')
        ->and($w->social_links)->toBe([
            'linkedin' => 'https://linkedin.com/company/acme',
            'twitter' => 'https://x.com/acme',
            'instagram' => 'https://instagram.com/acme',
        ]);
});

it('clears a field given empty', function (): void {
    artisan('employer:update', [
        'workspace' => $this->workspace->id,
        '--website' => '',
        '--gstin' => '',
        '--twitter' => '',
        '--linkedin' => '',
    ])->assertSuccessful();

    $w = $this->workspace->refresh();
    expect($w->website)->toBeNull()
        ->and($w->gstin)->toBeNull()
        ->and($w->social_links)->toBeNull()
        ->and($w->name)->toBe('Acme Technologies');
});

it('refuses an empty company name, a bad size or a non-URL social link', function (): void {
    artisan('employer:update', ['workspace' => $this->workspace->id, '--name' => ''])->assertFailed();
    artisan('employer:update', ['workspace' => $this->workspace->id, '--size' => 'lots'])->assertFailed();
    artisan('employer:update', ['workspace' => $this->workspace->id, '--facebook' => 'not a url'])->assertFailed();

    expect($this->workspace->refresh()->name)->toBe('Acme Technologies');
});

it('fails for an unknown workspace or when nothing is passed', function (): void {
    artisan('employer:update', ['workspace' => 999999, '--name' => 'X'])->assertFailed();
    artisan('employer:update', ['workspace' => $this->workspace->id])->assertFailed();
});
