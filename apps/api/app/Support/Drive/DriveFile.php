<?php

declare(strict_types=1);

namespace App\Support\Drive;

/** One file listed in a Drive folder. */
final class DriveFile
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $mimeType,
    ) {}
}
