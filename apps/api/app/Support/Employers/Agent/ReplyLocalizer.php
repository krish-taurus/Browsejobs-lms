<?php

declare(strict_types=1);

namespace App\Support\Employers\Agent;

use App\Enums\AiPurpose;
use App\Models\User;
use App\Services\AI\AiGateway;
use App\Support\Taurus\BrainStatus;
use Throwable;

/**
 * Gives Taurus's reply back in the language and script the client wrote in: a
 * client who writes in Hindi (Devanagari or in English letters), Kannada or
 * English is answered the same way. Taurus still works in English inside (the
 * planner, the proposals, the rules); this only rewrites the words the client
 * reads, and it never gets in the way:
 *
 *  - English in, English out, with no model call;
 *  - if the model cannot be reached, or answers with something that dropped a
 *    link, a role number or the YES / NO the client must type, the English
 *    reply is sent as it was.
 */
final readonly class ReplyLocalizer
{
    public function __construct(
        private AiGateway $ai,
        private BrainStatus $brain,
    ) {}

    public function localize(?User $user, ?string $clientSaid, string $reply): string
    {
        if ($user === null || $clientSaid === null || trim($reply) === '' || ! ClientLanguage::needsLocalizing($clientSaid)) {
            return $reply;
        }

        $active = $this->brain->active();

        try {
            $result = $this->ai->complete($user, AiPurpose::Taurus, 'taurus_localize', 1, [
                'said' => mb_substr(trim($clientSaid), 0, 600),
                'reply' => $reply,
            ], ['max_tokens' => 1200, ...($active !== null ? BrainStatus::gatewayOptions($active) : [])]);
        } catch (Throwable $e) {
            report($e);

            return $reply;
        }

        $out = trim($result->text);

        return $this->keepsWhatMatters($reply, $out) ? $out : $reply;
    }

    /** Links, role numbers and the typed commands must survive the rewrite. */
    private function keepsWhatMatters(string $reply, string $out): bool
    {
        if ($out === '' || mb_strlen($out) > mb_strlen($reply) * 3 + 300) {
            return false;
        }

        preg_match_all('#https?://[^\s)>\]]+#', $reply, $urls);
        foreach ($urls[0] as $url) {
            if (! str_contains($out, $url)) {
                return false;
            }
        }
        preg_match_all('/#\d{1,6}/', $reply, $ids);
        foreach ($ids[0] as $id) {
            if (! str_contains($out, $id)) {
                return false;
            }
        }
        foreach (['YES', 'NO', 'CANCEL', 'ALL'] as $command) {
            if (str_contains($reply, "*{$command}*") && ! str_contains($out, $command)) {
                return false;
            }
        }

        return true;
    }
}
