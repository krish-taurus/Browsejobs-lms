<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\EmployerWorkspace;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

/**
 * Edits an employer workspace's company details — what the CRM's "Edit
 * employer" form saves. Only the options given are changed; an option given
 * empty (--website=) clears that field. The owner and their sign-in are not
 * touched here, and the slug stays put so links to the company keep working.
 *
 *   php artisan employer:update 1 --name="Acme Ltd" --city=Bengaluru --twitter=
 */
final class UpdateEmployerCommand extends Command
{
    protected $signature = 'employer:update
        {workspace : Employer workspace id}
        {--name= : Company name}
        {--website=}
        {--industry=}
        {--size= : Employee count band, e.g. 11-50}
        {--gstin=}
        {--city= : Primary hiring location}
        {--linkedin=}
        {--instagram=}
        {--facebook=}
        {--twitter=}';

    protected $description = "Edit an employer workspace's company details.";

    public const SIZES = ['1-10', '11-50', '51-200', '201-500', '501-1000', '1000+'];

    private const NETWORKS = ['linkedin', 'instagram', 'facebook', 'twitter'];

    public function handle(): int
    {
        $workspace = EmployerWorkspace::withoutGlobalScopes()->find((int) $this->argument('workspace'));
        if ($workspace === null) {
            $this->error('No employer workspace with that id.');

            return self::FAILURE;
        }

        // Only what was actually passed: null = not given, '' = clear it.
        $given = [];
        foreach (['name', 'website', 'industry', 'size', 'gstin', 'city', ...self::NETWORKS] as $key) {
            $value = $this->option($key);
            if ($value !== null) {
                $given[$key] = trim((string) $value);
            }
        }

        if ($given === []) {
            $this->error('Nothing to change — pass at least one option.');

            return self::FAILURE;
        }

        $validator = Validator::make($given, [
            'name' => ['sometimes', 'required', 'string', 'max:150'],
            'website' => ['sometimes', 'nullable', 'string', 'max:190'],
            'industry' => ['sometimes', 'nullable', 'string', 'max:120'],
            'size' => ['sometimes', 'nullable', 'in:'.implode(',', self::SIZES)],
            'gstin' => ['sometimes', 'nullable', 'string', 'max:20'],
            'city' => ['sometimes', 'nullable', 'string', 'max:120'],
            'linkedin' => ['sometimes', 'nullable', 'url', 'max:255'],
            'instagram' => ['sometimes', 'nullable', 'url', 'max:255'],
            'facebook' => ['sometimes', 'nullable', 'url', 'max:255'],
            'twitter' => ['sometimes', 'nullable', 'url', 'max:255'],
        ], ['name.required' => 'The company name cannot be empty.']);

        if ($validator->fails()) {
            $this->error($validator->errors()->first());

            return self::FAILURE;
        }

        $blank = static fn (string $v): ?string => $v === '' ? null : $v;
        $changes = [];

        foreach (['name' => 'name', 'website' => 'website', 'industry' => 'industry', 'size' => 'company_size', 'gstin' => 'gstin'] as $key => $column) {
            if (array_key_exists($key, $given)) {
                $changes[$column] = $blank($given[$key]);
            }
        }

        if (array_key_exists('city', $given)) {
            // The first location is the primary city; any others are kept.
            $locations = array_values((array) ($workspace->locations ?? []));
            if ($given['city'] === '') {
                array_shift($locations);
            } else {
                $locations[0] = $given['city'];
            }
            $changes['locations'] = $locations === [] ? null : array_values($locations);
        }

        $links = (array) ($workspace->social_links ?? []);
        $touchedLinks = false;
        foreach (self::NETWORKS as $network) {
            if (array_key_exists($network, $given)) {
                $touchedLinks = true;
                if ($given[$network] === '') {
                    unset($links[$network]);
                } else {
                    $links[$network] = $given[$network];
                }
            }
        }
        if ($touchedLinks) {
            $changes['social_links'] = $links === [] ? null : $links;
        }

        $workspace->forceFill($changes)->save();

        $this->info("{$workspace->name} updated: ".implode(', ', array_keys($changes)).'.');

        return self::SUCCESS;
    }
}
