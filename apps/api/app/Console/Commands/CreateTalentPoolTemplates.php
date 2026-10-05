<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Submits the Talent Pool shortlist WhatsApp template to Meta for approval —
 * same shape as whatsapp:create-interview-templates, split into its own
 * command since this one has nothing to do with the interview pipeline.
 *
 * The body here MUST stay in step with the `params` order in
 * config/whatsapp_templates.php (name, role, company) — Meta fills {{1}},
 * {{2}}, {{3}} positionally.
 *
 * Safe to re-run — a template that already exists comes back as a
 * duplicate error and is reported, not retried.
 */
final class CreateTalentPoolTemplates extends Command
{
    protected $signature = 'whatsapp:create-talent-templates {--dry : Print what would be submitted and stop}';

    protected $description = 'Submit the Talent Pool shortlist WhatsApp template to Meta for approval.';

    public function handle(): int
    {
        $name = 'bj_talent_shortlisted';
        // params: name, role, company
        $body = "Hi {{1}}, good news — your CV has been shortlisted for the {{2}} role at {{3}} on BrowseJobs.\n\nPlease be ready: an interview call can happen any time. Keep an eye on your phone and email.";
        $example = ['Anjali Sharma', 'Data Analyst', 'BrowseJobs'];

        if ($this->option('dry')) {
            $this->line("── {$name} ".str_repeat('─', max(0, 40 - strlen($name))));
            $this->line($body);

            return self::SUCCESS;
        }

        $waba = (string) config('services.whatsapp.business_account_id');
        $token = (string) config('services.whatsapp.access_token');
        $base = rtrim((string) config('services.whatsapp.base_url'), '/');

        if ($waba === '' || $token === '') {
            $this->error('WhatsApp business account / token not configured.');

            return self::FAILURE;
        }

        $response = Http::withToken($token)->post("{$base}/{$waba}/message_templates", [
            'name' => $name,
            'language' => 'en',
            'category' => 'UTILITY',
            'components' => [[
                'type' => 'BODY',
                'text' => $body,
                'example' => ['body_text' => [$example]],
            ]],
        ]);

        if ($response->successful()) {
            $this->info("Submitted {$name} — status ".($response->json('status') ?? 'PENDING'));
            $this->newLine();
            $this->info('Meta usually reviews within minutes to a few hours.');
            $this->info('whatsapp:sync-templates runs hourly and activates it automatically once approved — no further action needed.');

            return self::SUCCESS;
        }

        $error = (string) $response->json('error.error_user_msg', $response->json('error.message', 'unknown error'));
        $this->warn("{$name}: {$error}");

        return self::FAILURE;
    }
}
