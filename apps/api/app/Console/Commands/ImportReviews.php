<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Review;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Bulk-imports REAL reviews from a CSV export (Google Business / WhatsApp).
 *
 * CSV header: author_name,rating,body,source,reviewed_on,course_slug
 *   - source: google | whatsapp | platform
 *   - reviewed_on (optional): YYYY-MM-DD
 *
 * Usage: php artisan reviews:import storage/imports/google-reviews.csv
 */
final class ImportReviews extends Command
{
    protected $signature = 'reviews:import {path : CSV file path} {--tenant=browsejobs}';

    protected $description = 'Import reviews from a Google/WhatsApp CSV export';

    public function handle(): int
    {
        $path = (string) $this->argument('path');

        if (! is_readable($path)) {
            $this->error("Cannot read file: {$path}");

            return self::FAILURE;
        }

        $tenant = Tenant::query()->where('slug', $this->option('tenant'))->first();
        if ($tenant === null) {
            $this->error("Unknown tenant: {$this->option('tenant')}");

            return self::FAILURE;
        }

        $handle = fopen($path, 'rb');
        if ($handle === false) {
            $this->error('Could not open file.');

            return self::FAILURE;
        }

        $header = fgetcsv($handle);
        if ($header === false) {
            $this->error('Empty file.');
            fclose($handle);

            return self::FAILURE;
        }

        $columns = array_map(fn ($col) => strtolower(trim((string) $col)), $header);
        $imported = 0;
        $skipped = 0;

        while (($row = fgetcsv($handle)) !== false) {
            $data = array_combine($columns, array_pad($row, count($columns), null));

            $name = trim((string) ($data['author_name'] ?? ''));
            $body = trim((string) ($data['body'] ?? ''));
            $rating = (int) ($data['rating'] ?? 0);
            $source = strtolower(trim((string) ($data['source'] ?? '')));

            if ($name === '' || $body === '' || $rating < 1 || $rating > 5
                || ! in_array($source, Review::SOURCES, true)) {
                $skipped++;

                continue;
            }

            Review::query()->create([
                'tenant_id' => $tenant->id,
                'author_name' => $name,
                'body' => $body,
                'rating' => $rating,
                'source' => $source,
                'course_slug' => ($data['course_slug'] ?? null) ?: null,
                'reviewed_on' => ! empty($data['reviewed_on'])
                    ? Carbon::parse((string) $data['reviewed_on'])->toDateString()
                    : null,
            ]);
            $imported++;
        }

        fclose($handle);

        $this->info("Imported {$imported} reviews; skipped {$skipped} invalid rows.");

        return self::SUCCESS;
    }
}
