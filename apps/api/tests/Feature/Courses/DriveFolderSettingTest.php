<?php

declare(strict_types=1);

use App\Models\PlatformSetting;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Drive\DriveFolder;
use App\Support\Settings\PlatformSettings;
use Database\Seeders\RolePermissionSeeder;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\putJson;

it('extracts a folder id from any pasted Drive link or a bare id', function () {
    expect(DriveFolder::id('https://drive.google.com/drive/folders/1SBPcVQCvZLc2EEiYHT1_U_ovRBPsd-Ls'))->toBe('1SBPcVQCvZLc2EEiYHT1_U_ovRBPsd-Ls')
        ->and(DriveFolder::id('https://drive.google.com/drive/u/0/folders/1ABC_def-123?usp=sharing'))->toBe('1ABC_def-123')
        ->and(DriveFolder::id('https://drive.google.com/open?id=1XYZ_folder-99'))->toBe('1XYZ_folder-99')
        ->and(DriveFolder::id('1SBPcVQCvZLc2EEiYHT1_U_ovRBPsd-Ls'))->toBe('1SBPcVQCvZLc2EEiYHT1_U_ovRBPsd-Ls')
        ->and(DriveFolder::id(''))->toBeNull()
        ->and(DriveFolder::id('not a link'))->toBeNull();
});

it('surfaces the Google Drive group in the admin settings schema', function () {
    $schema = app(PlatformSettings::class)->schema();
    $group = collect($schema)->firstWhere('key', 'google_drive');

    expect($group)->not->toBeNull();
    $keys = collect($group['fields'])->pluck('key');
    expect($keys)->toContain('reviews_folder')->toContain('service_account_json');
});

it('lets a super-admin save a new Drive folder link and read it back (secret stays server-side)', function () {
    $this->seed(RolePermissionSeeder::class);
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->for($tenant)->create(['user_type' => 'staff']);
    $admin->assignRole('super-admin');
    Sanctum::actingAs($admin);

    putJson('/api/v1/admin/settings', ['settings' => [
        'google_drive' => [
            'reviews_folder' => 'https://drive.google.com/drive/folders/NEWFOLDER_123',
            'service_account_json' => '{"type":"service_account","private_key":"x"}',
        ],
    ]])->assertOk();

    // Stored + applied over config; the plain folder link round-trips, the JSON is a masked secret.
    expect(config('services.google_drive.reviews_folder'))->toBe('https://drive.google.com/drive/folders/NEWFOLDER_123')
        ->and(DriveFolder::id(config('services.google_drive.reviews_folder')))->toBe('NEWFOLDER_123');

    $group = collect(app(PlatformSettings::class)->schema())->firstWhere('key', 'google_drive');
    $folderField = collect($group['fields'])->firstWhere('key', 'reviews_folder');
    $jsonField = collect($group['fields'])->firstWhere('key', 'service_account_json');
    expect($folderField['value'])->toBe('https://drive.google.com/drive/folders/NEWFOLDER_123')
        ->and($jsonField['value'])->toBeNull()          // secret never returned
        ->and($jsonField['set'])->toBeTrue();

    expect(PlatformSetting::query()->where('group', 'google_drive')->where('key', 'reviews_folder')->exists())->toBeTrue();
});
