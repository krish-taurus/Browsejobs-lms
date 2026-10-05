<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Submits the "class starting soon" WhatsApp template to Meta for approval
 * (Sept 2026) — sent by the CRM's own NotifyHostsClassStartingSoon command
 * (browsejobs-crm) 15 minutes before each class, to every HR Manager, with
 * the Zoom host start link.
 *
 * The {{5}} placeholder is the raw Zoom URL, on its own in the body text
 * (not a button) so WhatsApp auto-generates its usual link-preview card —
 * that's what actually produces the Zoom logo/title/domain preview, not
 * anything this template configures directly.
 *
 * Body params MUST stay in step with the order NotifyHostsClassStartingSoon
 * passes: staff name, class title, batch number, time, start url.
 */
final class CreateClassStartingSoonTemplate extends Command
{
    protected $signature = 'whatsapp:create-class-starting-template {--dry : Print what would be submitted and stop}';

    protected $description = 'Submit the "class starting soon" host-reminder WhatsApp template to Meta for approval.';

    public function handle(): int
    {
        $name = 'bj_class_starting_soon';
        // Meta rejects a template whose body starts or ends on a variable —
        // {{5}} (the link) needs a trailing word after it, or submission
        // fails outright.
        $body = "Hi {{1}}, your class \"{{2}}\" ({{3}}) starts at {{4}} IST.\n\nStart the meeting as host here:\n{{5}}\n\nSee you there!";
        $example = ['Priya Sharma', 'Introduction to Python', 'DE-202608-100', '9:00 pm', 'https://us06web.zoom.us/j/85613459047'];

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
            $this->info('The CRM calls this template by name directly (WhatsAppService::sendTemplate) — no sync step needed here. Every send attempt fails quietly until Meta approves it, then the very next class in the 15-minute window goes through automatically.');

            return self::SUCCESS;
        }

        $error = (string) $response->json('error.error_user_msg', $response->json('error.message', 'unknown error'));
        $this->warn("{$name}: {$error}");

        return self::FAILURE;
    }
}
