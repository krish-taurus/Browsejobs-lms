<?php

declare(strict_types=1);

namespace App\Support\Employers\Agent;

use App\Enums\AiPurpose;
use App\Models\EmployerJob;
use App\Models\EmployerJobApplication;
use App\Models\EmployerWorkspace;
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

    /** Languages a client can pick in the console; the message alone decides everywhere else. */
    private const PICKED = ['hi' => 'Hindi', 'kn' => 'Kannada'];

    /** @param  string|null  $picked  'hi' or 'kn' when the recruiter chose that language in the console, whatever they typed */
    public function localize(?User $user, ?string $clientSaid, string $reply, ?string $picked = null, ?EmployerWorkspace $workspace = null): string
    {
        $language = self::PICKED[(string) $picked] ?? null;
        $written = $clientSaid !== null ? ClientLanguage::describe($clientSaid) : null;
        if ($user === null || trim($reply) === '' || ($language === null && $written === null)) {
            return $reply;
        }

        $active = $this->brain->active();

        // Long signed links are easily mangled by a model, so they never go to it: each becomes a {LINK1} marker and
        // is put back afterwards.
        $links = [];
        $masked = (string) preg_replace_callback('#https?://[^\s)>\]]+#', function (array $m) use (&$links): string {
            $links[] = $m[0];

            return '{LINK'.count($links).'}';
        }, $reply);

        // The same for people and roles: a name must come back exactly as it went in.
        $names = $this->knownNames($workspace);
        $masked = $this->mask($masked, $names);

        try {
            $result = $this->ai->complete($user, AiPurpose::Taurus, 'taurus_localize', 4, [
                'language' => $language !== null
                    ? "They chose {$language} in the console: write the reply in {$language}, in its own script, even if the reply below is in English."
                    : "The recruiter writes in: {$written}. Write the reply in that language and script.",
                'reply' => $masked['text'],
            ], ['max_tokens' => 1200, ...($active !== null ? BrainStatus::gatewayOptions($active) : [])]);
        } catch (Throwable $e) {
            report($e);

            return $reply;
        }

        $out = trim($result->text);
        foreach ($masked['map'] as $token => $original) {
            if (! str_contains($out, $token)) {
                return $reply;
            }
            $out = str_replace($token, $original, $out);
        }
        foreach (array_keys($links) as $i) {
            if (! str_contains($out, '{LINK'.($i + 1).'}')) {
                return $reply;
            }
            $out = str_replace('{LINK'.($i + 1).'}', $links[$i], $out);
        }

        return $this->keepsWhatMatters($reply, $out) ? $out : $reply;
    }

    /**
     * Role titles, then the words of candidate names, each swapped for {N1}, {N2}… so the model cannot change
     * or swap them. Longest first, so "Data Engineer" goes before "Data".
     *
     * @return list<string>
     */
    private function knownNames(?EmployerWorkspace $workspace): array
    {
        if ($workspace === null) {
            return [];
        }

        try {
            $titles = EmployerJob::query()->where('employer_workspace_id', $workspace->id)->pluck('title')->all();
            $people = EmployerJobApplication::query()
                ->whereIn('employer_job_id', EmployerJob::query()->select('id')->where('employer_workspace_id', $workspace->id))
                ->with('candidate:id,name')->limit(300)->get()
                ->flatMap(fn (EmployerJobApplication $a): array => preg_split('/[^\p{L}]+/u', (string) ($a->candidate?->name ?? ''), -1, PREG_SPLIT_NO_EMPTY) ?: [])
                ->filter(fn (string $w): bool => mb_strlen($w) >= 3)
                ->all();
        } catch (Throwable $e) {
            report($e);

            return [];
        }

        $all = array_values(array_unique(array_filter([...$titles, (string) $workspace->name, ...$people], fn ($n): bool => is_string($n) && mb_strlen(trim($n)) >= 3)));
        usort($all, fn (string $a, string $b): int => mb_strlen($b) <=> mb_strlen($a));

        return $all;
    }

    /**
     * @param  list<string>  $names
     * @return array{text: string, map: array<string, string>}
     */
    private function mask(string $text, array $names): array
    {
        $map = [];
        foreach ($names as $name) {
            $text = (string) preg_replace_callback('/(?<![\p{L}\p{N}])'.preg_quote($name, '/').'(?![\p{L}\p{N}])/iu', function (array $m) use (&$map): string {
                $token = '{N'.(count($map) + 1).'}';
                $map[$token] = $m[0];

                return $token;
            }, $text);
        }

        return ['text' => $text, 'map' => $map];
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
