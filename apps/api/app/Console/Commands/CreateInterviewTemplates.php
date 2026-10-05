<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Submits the five interview templates to Meta for approval.
 *
 * The bodies here MUST stay in step with the `params` order in
 * config/whatsapp_templates.php — Meta fills {{1}}, {{2}}, … positionally, so a
 * mismatched order sends a student someone else's name in the slot field with
 * nothing in the logs to say it went wrong.
 *
 * Meta's body rules this obeys: no placeholder at the very start or end of a
 * body, never two placeholders side by side, and an example value for each.
 *
 * Safe to re-run — a template that already exists comes back as a duplicate
 * error and is reported, not retried.
 */
final class CreateInterviewTemplates extends Command
{
    protected $signature = 'whatsapp:create-interview-templates {--dry : Print what would be submitted and stop}';

    protected $description = 'Submit the five interview WhatsApp templates to Meta for approval.';

    /**
     * @return list<array{name: string, body: string, example: list<string>}>
     */
    private function templates(): array
    {
        return [
            [
                'name' => 'bj_interview_applied',
                // params: student, round, stage, slot, url
                'body' => "New interview request.\n\n{{1}} has applied for Round {{2}} — {{3}}.\nSlot asked for: {{4}}\n\nApprove or move it here: {{5}}\nOpen the CRM to act on it.",
                'example' => ['Kunal Sharma', '1', 'Tech Mentor screen', 'Tue, 12 Aug 2026 - 4:30 PM IST', 'https://crm.browsejobs.ai/interviews'],
            ],
            [
                'name' => 'bj_interview_approved',
                // params: round, stage, slot, interviewer, url
                'body' => "Your interview is confirmed.\n\nRound {{1}} — {{2}}\nWhen: {{3}}\nWith: {{4}}\n\nSee the details and join from here: {{5}}\nPlease be ready five minutes early.",
                'example' => ['1', 'Tech Mentor screen', 'Tue, 12 Aug 2026 - 4:30 PM IST', 'Satyajeev Patnaik', 'https://browsejobs.ai/interviews'],
            ],
            [
                'name' => 'bj_interview_declined',
                // params: round, stage, note, url
                'body' => "About your Round {{1}} interview request.\n\n{{2}}\nThe panel could not take this booking.\n\nReason: {{3}}\n\nYou can pick another slot here: {{4}}\nSorry for the trouble.",
                'example' => ['1', 'Tech Mentor screen', 'Please finish module 3 first.', 'https://browsejobs.ai/interviews'],
            ],
            [
                'name' => 'bj_interview_rescheduled',
                // params: round, stage, was, slot, interviewer, url
                'body' => "Your interview has been moved.\n\nRound {{1}} — {{2}}\nWas: {{3}}\nNow: {{4}}\nWith: {{5}}\n\nSee the new details here: {{6}}\nSorry for the change.",
                'example' => ['1', 'Tech Mentor screen', 'Tue, 12 Aug 2026 - 4:30 PM IST', 'Thu, 14 Aug 2026 - 11:00 AM IST', 'Satyajeev Patnaik', 'https://browsejobs.ai/interviews'],
            ],
            [
                'name' => 'bj_interview_cleared',
                // params: round, stage, note, next, url
                'body' => "Congratulations, you cleared Round {{1}}.\n\n{{2}}\n\nFeedback from your interviewer: {{3}}\n\n{{4}}\nOpen your interview page: {{5}}\nWell done.",
                'example' => ['1', 'Tech Mentor screen', 'Strong on SQL, revise joins.', 'Your final round with the Tech Manager is now open.', 'https://browsejobs.ai/interviews'],
            ],
            [
                'name' => 'bj_interview_not_cleared',
                // params: round, stage, note, url
                'body' => "Your Round {{1}} result is in.\n\n{{2}}\nYou did not clear this round this time.\n\nFeedback from your interviewer: {{3}}\n\nYou can apply again when you are ready: {{4}}\nKeep going.",
                'example' => ['1', 'Tech Mentor screen', 'Revise joins and window functions.', 'https://browsejobs.ai/interviews'],
            ],
        ];
    }

    public function handle(): int
    {
        $waba = (string) config('services.whatsapp.business_account_id');
        $token = (string) config('services.whatsapp.access_token');
        $base = rtrim((string) config('services.whatsapp.base_url'), '/');

        if ($waba === '' || $token === '') {
            $this->error('WhatsApp business account / token not configured.');

            return self::FAILURE;
        }

        foreach ($this->templates() as $t) {
            if ($this->option('dry')) {
                $this->line("── {$t['name']} ".str_repeat('─', max(0, 40 - strlen($t['name']))));
                $this->line($t['body']);
                $this->newLine();

                continue;
            }

            $response = Http::withToken($token)->post("{$base}/{$waba}/message_templates", [
                'name' => $t['name'],
                'language' => 'en',
                'category' => 'UTILITY',
                'components' => [[
                    'type' => 'BODY',
                    'text' => $t['body'],
                    'example' => ['body_text' => [$t['example']]],
                ]],
            ]);

            if ($response->successful()) {
                $this->info("Submitted {$t['name']} — status ".($response->json('status') ?? 'PENDING'));

                continue;
            }

            $error = (string) $response->json('error.error_user_msg', $response->json('error.message', 'unknown error'));
            $this->warn("{$t['name']}: {$error}");
        }

        if (! $this->option('dry')) {
            $this->newLine();
            $this->info('Submitted. Meta usually reviews within minutes to a few hours.');
            $this->info('Run `whatsapp:sync-templates` once approved — it activates them automatically (it also runs hourly).');
        }

        return self::SUCCESS;
    }
}
