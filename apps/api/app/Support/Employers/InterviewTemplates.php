<?php

declare(strict_types=1);

namespace App\Support\Employers;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * The two WhatsApp templates the message agent sends first (a candidate has
 * not written to us, so free text is not allowed):
 *
 *  - INVITE: "<company> shortlisted you for <role> — which day suits you? 1/2/3"
 *  - LINK:   "your <round> interview is open now — start here: <link>"
 *
 * Both have to be approved by Meta before anything can go out. This class
 * submits them (once, from the AI shortlist tab) and reports their status.
 */
final class InterviewTemplates
{
    public const INVITE = 'bj_interview_invite';

    public const LINK = 'bj_interview_link';

    public const BODIES = [
        self::INVITE => "Hi {{1}}, {{2}} has shortlisted you for the {{3}} role.\n\nThe first round is a short AI interview you can take on your phone or laptop. Which day suits you?\n1) {{4}}\n2) {{5}}\n3) {{6}}\n\nReply 1, 2 or 3.",
        self::LINK => "Hi {{1}}, your {{2}} interview for the {{3}} role at {{4}} is open now.\n\nStart here: {{5}}\n\nLog in with this WhatsApp number. It takes about 15 minutes, so find a quiet place.",
    ];

    private const EXAMPLES = [
        self::INVITE => ['Asha', 'Incture', 'Data Engineer', 'Tue 14 Oct', 'Wed 15 Oct', 'Thu 16 Oct'],
        self::LINK => ['Asha', 'L1', 'Data Engineer', 'Incture', 'https://browsejobs.ai/hire-interview/AbC123'],
    ];

    /** @return array<string, string|null> template name => APPROVED / PENDING / REJECTED / null (never submitted) */
    public function statuses(bool $fresh = false): array
    {
        if ($fresh) {
            Cache::forget('interview_template_statuses');
        }

        return Cache::remember('interview_template_statuses', 120, function (): array {
            $out = [self::INVITE => null, self::LINK => null];
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
            return [self::INVITE => 'WhatsApp is not configured', self::LINK => 'WhatsApp is not configured'];
        }

        $result = [];
        foreach ($this->statuses(fresh: true) as $name => $status) {
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
}
