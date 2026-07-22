<?php

declare(strict_types=1);

use App\Actions\Reviews\SyncDriveReviews;
use App\Models\ReviewIntake;
use App\Models\Tenant;
use App\Support\Drive\DriveClient;
use App\Support\Drive\FakeDriveClient;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('s3');
    config(['services.google_drive.reviews_folder' => 'https://drive.google.com/drive/folders/FOLDER123']);
    $this->tenant = Tenant::factory()->create(['slug' => 'browsejobs']);
    $this->fake = new FakeDriveClient;
    app()->instance(DriveClient::class, $this->fake);
});

it('pulls new images into the intake queue and stores them', function () {
    $this->fake->add('f1', 'meera.jpg', 'image/jpeg', 'BYTES-1');
    $this->fake->add('f2', 'sujit.png', 'image/png', 'BYTES-2');

    expect(app(SyncDriveReviews::class)->handle())->toBe(2);

    $rows = ReviewIntake::withoutGlobalScopes()->get();
    expect($rows)->toHaveCount(2)
        ->and($rows->pluck('status')->unique()->all())->toBe([ReviewIntake::STATUS_PENDING])
        ->and($rows->firstWhere('drive_file_id', 'f1')->file_name)->toBe('meera.jpg');

    // Each image landed on s3 under the tenant.
    foreach ($rows as $row) {
        Storage::disk('s3')->assertExists($row->image_path);
        expect($row->image_path)->toContain("review-intake/{$this->tenant->id}");
    }
});

it('is idempotent — a Drive file is never imported twice', function () {
    $this->fake->add('f1', 'a.jpg', 'image/jpeg', 'X');

    expect(app(SyncDriveReviews::class)->handle())->toBe(2 - 1); // 1 new
    // Second run: same file already in intake → nothing new, even after adding one more.
    $this->fake->add('f2', 'b.jpg', 'image/jpeg', 'Y');
    expect(app(SyncDriveReviews::class)->handle())->toBe(1);

    expect(ReviewIntake::withoutGlobalScopes()->count())->toBe(2);
});

it('ignores non-image files in the folder', function () {
    $this->fake->add('img', 'photo.jpg', 'image/jpeg', 'X');
    $this->fake->add('doc', 'notes.pdf', 'application/pdf', 'Y');

    expect(app(SyncDriveReviews::class)->handle())->toBe(1);
    expect(ReviewIntake::withoutGlobalScopes()->pluck('drive_file_id')->all())->toBe(['img']);
});

it('counts visible images without importing (connection check)', function () {
    $this->fake->add('f1', 'a.jpg', 'image/jpeg', 'X');
    $this->fake->add('f2', 'b.png', 'image/png', 'Y');

    expect(app(SyncDriveReviews::class)->count())->toBe(2)
        ->and(ReviewIntake::withoutGlobalScopes()->count())->toBe(0); // nothing imported
});

it('does nothing when no folder is configured', function () {
    config(['services.google_drive.reviews_folder' => '']);
    $this->fake->add('f1', 'a.jpg', 'image/jpeg', 'X');

    expect(app(SyncDriveReviews::class)->handle())->toBe(0);
    expect(ReviewIntake::withoutGlobalScopes()->count())->toBe(0);
});
