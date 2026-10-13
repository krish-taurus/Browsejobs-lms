<?php

declare(strict_types=1);

namespace App\Support\Employers;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * The WhatsApp templates the message agent sends first (the other side has
 * not written to us in the last 24 hours, so free text is not allowed):
 *
 *  - INVITE:     "<company> shortlisted you for <role> — which slot suits you? 1/2/3"
 *  - LINK:       "your <round> interview is ready — reply OK for the link"
 *  - NEXT_ROUND: "you cleared <round> — which slot suits you for the next one? 1/2/3"
 *  - RESULT:     to the client: "<candidate> cleared both AI rounds — reply REPORT"
 *  - UPDATE:     to the client: one line about a stage (booked, scored, missed …)
 *
 * The link template carries no URL: Meta classed a URL variable as marketing
 * (and marketing sends are frequency-capped per person). The candidate's
 * reply opens the 24-hour window, and the link then goes as free text.
 *
 * Each has to be approved by Meta before it can go out. This class submits
 * them (from the AI shortlist tab, or `interviews:submit-templates`) and
 * reports their status.
 */
final class InterviewTemplates
{
    public const INVITE = 'bj_interview_invite';

    public const LINK = 'bj_interview_link';

    public const NEXT_ROUND = 'bj_interview_next_round';

    public const RESULT = 'bj_interview_result';

    public const UPDATE = 'bj_hiring_update';

    public const BODIES = [
        self::INVITE => "Hi {{1}}, {{2}} has shortlisted you for the {{3}} role.\n\nThe first round is a short AI interview you can take on your phone or laptop. Which day suits you?\n1) {{4}}\n2) {{5}}\n3) {{6}}\n\nReply 1, 2 or 3.",
        self::LINK => "Hi {{1}}, your {{2}} interview for the {{3}} role is ready.\n\nReply OK and we will send your interview link here.",
        self::NEXT_ROUND => "Hi {{1}}, you cleared the {{2}} interview for the {{3}} role at {{4}}.\n\nThe next round is another short AI interview. Which slot suits you?\n1) {{5}}\n2) {{6}}\n3) {{7}}\n\nReply 1, 2 or 3.",
        self::RESULT => "Hi {{1}}, {{2}} has cleared both AI interview rounds for your {{3}} role, scoring {{4}} in L1 and {{5}} in L2.\n\nReply REPORT to see the full report and the interview recordings.",
        self::UPDATE => "Hi {{1}}, an update on hiring for your {{2}} role: {{3}}\n\nReply REPORT to see every candidate's status.",
    ];

    private const EXAMPLES = [
        self::INVITE => ['Asha', 'Incture', 'Data Engineer', 'Tue 14 Oct', 'Wed 15 Oct', 'Thu 16 Oct'],
        self::LINK => ['Asha', 'L1', 'Data Engineer'],
        self::NEXT_ROUND => ['Asha', 'L1', 'Data Engineer', 'Incture', 'Tue 14 Oct, 11:00 AM', 'Wed 15 Oct, 11:00 AM', 'Thu 16 Oct, 11:00 AM'],
        self::RESULT => ['Ravi', 'Asha Rao', 'Data Engineer', '78', '82'],
        self::UPDATE => ['Ravi', 'Data Engineer', 'Asha Rao booked the L1 AI interview for Tue 14 Oct, 11:00 AM.'],
    ];

    /** @return array<string, string|null> template name => APPROVED / PENDING / REJECTED / null (never submitted) */
    public function statuses(bool $fresh = false): array
    {
        if ($fresh) {
            Cache::forget('interview_template_statuses');
        }

        return Cache::remember('interview_template_statuses', 120, function (): array {
            $out = array_fill_keys(array_keys(self::BODIES), null);
            $waba = (string) config('services.whatsapp.business_account_id');
            $token = (string) config('services.whatsapp.access_token');
            if ($waba === '' || $token === '') {
                return $out;
            }

            foreach (array_keys($out) as $name) {
                try {
                    $row = collect(Http::withToken($token)->timeout(15)
                        ->get(rtrim((string) config('services.whatsapp.base_url'), '/')."/{$waba}/message_templates", ['name' => $name, 'fields' => 'name,status'])
                        ->json('data') ?? [])->firstWhere('name', $name);
                    $out[$name] = is_array($row) ? (string) ($row['status'] ?? '') : null;
                } catch (Throwable) {
                    $out[$name] = null;
                }
            }

            return $out;
        });
    }

    public function approved(string $name): bool
    {
        return ($this->statuses()[$name] ?? null) === 'APPROVED';
    }

    /**
     * Submit whichever templates were never submitted.
     *
     * @return array<string, string> template name => what happened
     */
    public function submitMissing(): array
    {
        $waba = (string) config('services.whatsapp.business_account_id');
        $token = (string) config('services.whatsapp.access_token');
        if ($waba === '' || $token === '') {
            return array_fill_keys(array_keys(self::BODIES), 'WhatsApp is not configured');
        }

        $result = [];
        foreach ($this->statuses(fresh: true) as $name => $status) {
            if ($status === 'REJECTED') {
                $result[$name] = $this->edit($name, $waba, $token);

                continue;
            }
            if ($status !== null) {
                $result[$name] = strtolower($status);

                continue;
            }

            try {
                $response = Http::withToken($token)->timeout(20)->post(
                    rtrim((string) config('services.whatsapp.base_url'), '/')."/{$waba}/message_templates",
                    [
                        'name' => $name,
                        'language' => 'en',
                        'category' => 'UTILITY',
                        'components' => [[
                            'type' => 'BODY',
                            'text' => self::BODIES[$name],
                            'example' => ['body_text' => [self::EXAMPLES[$name]]],
                        ]],
                    ],
                );
                $result[$name] = $response->successful()
                    ? 'submitted ('.strtolower((string) $response->json('status', 'pending')).')'
                    : 'refused: '.(string) ($response->json('error.error_user_msg') ?? $response->json('error.message') ?? 'HTTP '.$response->status());
            } catch (Throwable $e) {
                $result[$name] = 'could not reach WhatsApp: '.$e->getMessage();
            }
        }

        Cache::forget('interview_template_statuses');

        return $result;
    }

    /** Rewrite a rejected template with the current body; it goes back to review. */
    private function edit(string $name, string $waba, string $token): string
    {
        $base = rtrim((string) config('services.whatsapp.base_url'), '/');

        try {
            $id = collect(Http::withToken($token)->timeout(15)
                ->get("{$base}/{$waba}/message_templates", ['name' => $name, 'fields' => 'id,name'])
                ->json('data') ?? [])->firstWhere('name', $name)['id'] ?? null;
            if (! is_string($id) || $id === '') {
                return 'rejected (could not find it to edit)';
            }

            $response = Http::withToken($token)->timeout(20)->post("{$base}/{$id}", [
                'category' => 'UTILITY',
                'components' => [[
                    'type' => 'BODY',
                    'text' => self::BODIES[$name],
                    'example' => ['body_text' => [self::EXAMPLES[$name]]],
                ]],
            ]);

            return $response->successful()
                ? 'resubmitted after rejection'
                : 'rejected; edit refused: '.(string) ($response->json('error.error_user_msg') ?? $response->json('error.message') ?? 'HTTP '.$response->status());
        } catch (Throwable $e) {
            return 'rejected; could not reach WhatsApp: '.$e->getMessage();
        }
    }
}
