<?php

declare(strict_types=1);

namespace App\Support\Drive;

/** No-op Drive client — bound when no service account is configured. */
final class NullDriveClient implements DriveClient
{
    public function listImages(string $folderId): array
    {
        return [];
    }

    public function download(string $fileId): string
    {
        return '';
    }
}
