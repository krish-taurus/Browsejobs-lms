<?php

declare(strict_types=1);

namespace App\Support\Drive;

/** In-memory Drive client for tests. */
final class FakeDriveClient implements DriveClient
{
    /** @var array<string, array{file: DriveFile, bytes: string}> */
    public array $files = [];

    public function add(string $id, string $name, string $mimeType, string $bytes): void
    {
        $this->files[$id] = ['file' => new DriveFile($id, $name, $mimeType), 'bytes' => $bytes];
    }

    public function listImages(string $folderId): array
    {
        return array_values(array_map(
            fn (array $f) => $f['file'],
            array_filter($this->files, fn (array $f) => str_starts_with($f['file']->mimeType, 'image/')),
        ));
    }

    public function download(string $fileId): string
    {
        return $this->files[$fileId]['bytes'] ?? '';
    }
}
