<?php

declare(strict_types=1);

namespace App\Actions\Reviews;

use App\Models\ReviewIntake;
use App\Models\Tenant;
use App\Support\Drive\DriveClient;
use App\Support\Drive\DriveFile;
use App\Support\Drive\DriveFolder;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Pulls new review images from the configured Google Drive folder into the intake
 * queue (Platform Spec §3). Idempotent: a file already in intake (by drive_file_id)
 * is skipped, so a weekly run only ever adds what's new — safe over a folder that
 * keeps growing. Nothing is published automatically; an admin triages each.
 */
final readonly class SyncDriveReviews
{
    public function __construct(private DriveClient $drive) {}

    /** How many images are in the folder (a connection/visibility check). */
    public function count(): int
    {
        $folderId = DriveFolder::id(config('services.google_drive.reviews_folder'));

        return $folderId === null ? 0 : count($this->drive->listImages($folderId));
    }

    /** @return int number of new intake rows created */
    public function handle(): int
    {
        $folderId = DriveFolder::id(config('services.google_drive.reviews_folder'));
        $tenant = $this->tenant();
        if ($folderId === null || $tenant === null) {
            return 0;
        }

        return app(TenantContext::class)->run($tenant, function () use ($folderId, $tenant): int {
            $existing = ReviewIntake::query()->pluck('drive_file_id')->flip();
            $created = 0;

            foreach ($this->drive->listImages($folderId) as $file) {
                if ($existing->has($file->id)) {
                    continue;
                }

                $bytes = $this->drive->download($file->id);
                if ($bytes === '') {
                    continue;
                }

                $path = "review-intake/{$tenant->id}/{$file->id}.".$this->extension($file);
                Storage::disk('s3')->put($path, $bytes);

                ReviewIntake::query()->create([
                    'tenant_id' => $tenant->id,
                    'drive_file_id' => $file->id,
                    'file_name' => $file->name,
                    'image_path' => $path,
                    'status' => ReviewIntake::STATUS_PENDING,
                ]);
                $created++;
            }

            return $created;
        });
    }

    /** The platform tenant the reviews belong to (the folder is a platform-global setting). */
    private function tenant(): ?Tenant
    {
        return Tenant::query()->where('slug', 'browsejobs')->first()
            ?? Tenant::query()->orderBy('id')->first();
    }

    private function extension(DriveFile $file): string
    {
        $ext = Str::afterLast($file->name, '.');
        if ($ext !== '' && $ext !== $file->name && strlen($ext) <= 5) {
            return strtolower($ext);
        }

        return match ($file->mimeType) {
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'jpg',
        };
    }
}
