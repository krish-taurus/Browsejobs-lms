<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Employers\OnboardEmployer;
use App\Models\EmployerWorkspace;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

/**
 * Onboard an employer without a browser, for the CRM's Employer Management
 * screen to call across the bridge.
 *
 * Deliberately not `employer:grant-owner`. That command prompts for a password
 * on the terminal, which makes it unusable from another process — and it sets
 * the employer's credential, which the onboarding path exists precisely to
 * avoid. This wraps the same OnboardEmployer action the admin panel uses, so
 * ops gets one behaviour whichever door they come through: workspace created,
 * owner seated, and a single-use invite the employer redeems themselves.
 */
final class OnboardEmployerCommand extends Command
{
    protected $signature = 'employer:onboard
        {company : Company name}
        {email : The owner\'s work email}
        {--owner-name= : Their name, for a new account}
        {--website=}
        {--industry=}
        {--size= : Employee count band, e.g. 11-50}
        {--gstin=}
        {--city= : Primary hiring location}
        {--linkedin=}
        {--instagram=}
        {--facebook=}
        {--twitter=}
        {--tenant= : Tenant id, when the install has more than one}';

    protected $description = 'Create an employer workspace, seat its owner and issue their invite.';

    public function handle(OnboardEmployer $onboard): int
    {
        $company = trim((string) $this->argument('company'));
        $email = mb_strtolower(trim((string) $this->argument('email')));

        if ($company === '') {
            $this->error('A company name is required.');

            return self::FAILURE;
        }

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error("[{$email}] is not a valid email address.");

            return self::FAILURE;
        }

        $tenant = $this->option('tenant') !== null
            ? Tenant::query()->find((int) $this->option('tenant'))
            : Tenant::query()->orderBy('id')->first();

        if ($tenant === null) {
            $this->error('No tenant found.');

            return self::FAILURE;
        }

        // Onboarding is audited against whoever did it. Run from the CRM there
        // is no signed-in user here, so it is attributed to the first admin —
        // the audit row still names a person rather than "system".
        $actor = User::query()->withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->whereIn('user_type', ['admin', 'staff'])
            ->orderBy('id')
            ->first();

        if ($actor === null) {
            $this->error('No admin account to attribute this to.');

            return self::FAILURE;
        }

        try {
            $result = $onboard->handle($tenant, [
                'company' => $company,
                'owner_email' => $email,
                'owner_name' => $this->option('owner-name'),
                'website' => $this->option('website'),
                'industry' => $this->option('industry'),
                'company_size' => $this->option('size'),
            ], $actor);
        } catch (ValidationException $e) {
            $this->error(collect($e->errors())->flatten()->first() ?? 'Could not onboard that employer.');

            return self::FAILURE;
        }

        /** @var EmployerWorkspace $workspace */
        $workspace = $result['workspace'];

        // The fields OnboardEmployer does not carry. Written here rather than
        // widening that action's contract, which the admin panel also uses.
        $extra = array_filter([
            'gstin' => $this->option('gstin'),
            'locations' => $this->option('city') ? [$this->option('city')] : null,
            'social_links' => $this->socialLinks(),
        ], static fn ($value) => $value !== null && $value !== '' && $value !== []);

        if ($extra !== []) {
            app(TenantContext::class)->run($tenant, static function () use ($workspace, $extra): void {
                $workspace->forceFill($extra)->save();
            });
        }

        $this->info("{$company} onboarded. {$email} is the owner.");
        $this->line('They set their own password with this invite, valid until '
            .($result['invite']->expires_at?->format('d M Y') ?? 'further notice').':');
        $this->line(rtrim((string) config('app.frontend_url', config('app.url')), '/')
            .'/employer/claim/'.$result['invite']->token);

        return self::SUCCESS;
    }

    /**
     * @return array<string, string>|null
     */
    private function socialLinks(): ?array
    {
        $links = [];

        foreach (['linkedin', 'instagram', 'facebook', 'twitter'] as $network) {
            $value = trim((string) $this->option($network));

            if ($value !== '') {
                $links[$network] = $value;
            }
        }

        return $links === [] ? null : $links;
    }
}
