<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\EmployerWorkspace;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Sets or removes an employer's company logo — the mark shown on their job
 * cards in Jobs for You. Run by the CRM's Employer Management page (the CRM
 * never writes LMS storage itself), or by hand:
 *
 *   php artisan employer:set-logo 1 /path/to/logo.png
 *   php artisan employer:set-logo 1 --remove
 *
 * The image is copied into the public disk under employer-logos/, so it is
 * served from {APP_URL}/storage/employer-logos/…; the previous file is
 * deleted. PNG, JPEG and WebP only — SVG can carry script, so it is refused.
 */
final class SetEmployerLogoCommand extends Command
{
    protected $signature = 'employer:set-logo
        {workspace : Employer workspace id}
        {path? : Image file to use as the logo}
        {--remove : Clear the logo instead}';

    protected $description = "Set or remove an employer workspace's company logo.";

    public const FOLDER = 'employer-logos';

    public const MAX_BYTES = 2 * 1024 * 1024;

    /** @var array<string, string> mime => extension */
    private const TYPES = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'];

    public function handle(): int
    {
        $workspace = EmployerWorkspace::withoutGlobalScopes()->find((int) $this->argument('workspace'));
        if ($workspace === null) {
            $this->error('No employer workspace with that id.');

            return self::FAILURE;
        }

        $disk = Storage::disk('public');
        $previous = $workspace->logo_path;

        if ($this->option('remove')) {
            $workspace->forceFill(['logo_path' => null])->save();
            $this->deleteOwned($previous);
            $this->info("Logo removed for {$workspace->name}.");

            return self::SUCCESS;
        }

        $path = (string) $this->argument('path');
        if ($path === '' || ! is_file($path) || ! is_readable($path)) {
            $this->error('Give the path of a readable image file, or --remove.');

            return self::FAILURE;
        }

        $size = (int) filesize($path);
        if ($size === 0 || $size > self::MAX_BYTES) {
            $this->error('The logo must be an image of 2 MB or less.');

            return self::FAILURE;
        }

        $mime = (string) (new \finfo(FILEINFO_MIME_TYPE))->file($path);
        $dimensions = @getimagesize($path);
        if (! isset(self::TYPES[$mime]) || $dimensions === false) {
            $this->error('The logo must be a PNG, JPEG or WebP image.');

            return self::FAILURE;
        }

        $contents = (string) file_get_contents($path);
        $target = self::FOLDER.'/'.$workspace->id.'-'.substr(hash('sha256', $contents), 0, 16).'.'.self::TYPES[$mime];
        $disk->put($target, $contents, 'public');

        $workspace->forceFill(['logo_path' => $target])->save();
        if ($previous !== $target) {
            $this->deleteOwned($previous);
        }

        $this->info("Logo set for {$workspace->name}: ".$workspace->logoUrl());

        return self::SUCCESS;
    }

    /** Only ever delete files this command wrote — never an arbitrary path. */
    private function deleteOwned(?string $path): void
    {
        if ($path !== null && str_starts_with($path, self::FOLDER.'/') && ! str_contains($path, '..')) {
            Storage::disk('public')->delete($path);
        }
    }
}
