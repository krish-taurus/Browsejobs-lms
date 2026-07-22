<?php

declare(strict_types=1);

namespace App\Support\Drive;

/**
 * Read-only Google Drive access for the reviews-folder sync. Every implementation
 * is called only from the queued/scheduled sync (never the request cycle). Tests
 * bind a fake; production binds the real client when a service account is set,
 * otherwise a safe no-op Null client.
 */
interface DriveClient
{
    /**
     * Image files directly inside the folder.
     *
     * @return list<DriveFile>
     */
    public function listImages(string $folderId): array;

    /** Raw bytes of a file. */
    public function download(string $fileId): string;
}
