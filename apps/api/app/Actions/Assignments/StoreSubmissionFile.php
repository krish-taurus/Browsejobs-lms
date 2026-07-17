<?php

declare(strict_types=1);

namespace App\Actions\Assignments;

use App\Models\User;
use Illuminate\Http\UploadedFile;

/**
 * Stores a student's single assignment deliverable on s3 under
 * assignments/{tenant_id}/… and returns the file columns for the submission row
 * (same tenant-prefixed convention as ticket attachments / receipts).
 */
final readonly class StoreSubmissionFile
{
    /**
     * @return array{file_path: string, file_name: string, file_mime: string, file_size: int}
     */
    public function handle(User $student, UploadedFile $file): array
    {
        $path = $file->store("assignments/{$student->tenant_id}", 's3');

        return [
            'file_path' => (string) $path,
            'file_name' => $file->getClientOriginalName(),
            'file_mime' => $file->getClientMimeType(),
            'file_size' => $file->getSize() ?: 0,
        ];
    }
}
