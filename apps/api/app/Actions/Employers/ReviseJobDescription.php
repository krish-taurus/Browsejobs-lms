<?php

declare(strict_types=1);

namespace App\Actions\Employers;

use App\Enums\AiPurpose;
use App\Models\User;
use App\Services\AI\AiGateway;
use App\Services\AI\JsonOutput;
use App\Support\Tenancy\TenantContext;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Change one thing about a draft JD, on the employer's instruction.
 *
 * The console could previously only start over. Say "add a preferred-skills
 * bullet for 3+ years of JavaScript" to a finished draft and it heard a new
 * hiring request, found no job title in it, and asked what role you were
 * hiring for — throwing away the JD you were in the middle of.
 *
 * Editing by voice is the point of a console you talk to, so this exists to
 * make the second sentence mean something. The model is given the draft as it
 * currently stands and told to change only what was asked; it returns the
 * whole description, because a fragment cannot be merged reliably.
 */
final readonly class ReviseJobDescription
{
    public function __construct(private AiGateway $ai) {}

    /**
     * @param  list<string>  $skills
     * @return array{description: string, skills: list<string>, summary: string}
     */
    public function handle(User $actor, string $title, string $description, array $skills, string $instruction): array
    {
        $instruction = trim($instruction);

        if ($instruction === '') {
            throw ValidationException::withMessages(['instruction' => 'Say what you would like changed.']);
        }

        return app(TenantContext::class)->run($actor->tenant, function () use ($actor, $title, $description, $skills, $instruction): array {
            try {
                $result = $this->ai->complete($actor, AiPurpose::Content, 'jd_revise', 1, [
                    'title' => $title,
                    'description' => mb_substr($description, 0, 6000),
                    'skills' => implode(', ', $skills) ?: 'none yet',
                    'instruction' => mb_substr($instruction, 0, 600),
                ], ['max_tokens' => 1800]);

                $parsed = JsonOutput::object($result->text);
            } catch (Throwable) {
                throw ValidationException::withMessages([
                    'instruction' => 'Could not make that change right now — edit the draft directly instead.',
                ]);
            }

            $revised = is_array($parsed) && is_string($parsed['description'] ?? null)
                ? $this->stripEchoedLabels($parsed['description'])
                : '';

            // An empty or truncated answer would wipe the JD they are working
            // on. Keep what they had and say so.
            if ($revised === '') {
                throw ValidationException::withMessages([
                    'instruction' => ($result->stopReason === 'length' || $result->stopReason === 'max_tokens')
                        ? 'That draft is too long for me to rewrite in one go — edit it directly.'
                        : 'Could not make that change. Try wording it differently, or edit the draft directly.',
                ]);
            }

            return [
                'description' => $revised,
                'skills' => $this->normaliseSkills($parsed['skills'] ?? $skills, $skills),
                'summary' => is_string($parsed['summary'] ?? null) && trim($parsed['summary']) !== ''
                    ? trim($parsed['summary'])
                    : 'Updated the draft.',
            ];
        });
    }

    /**
     * Drop any header the model copied out of the prompt.
     *
     * Asked to return a description, models sometimes hand back the labels
     * they were shown it under — "Title: Data Engineer", "Description:", the
     * fence markers. The prompt forbids it; this makes sure it can never reach
     * a published JD regardless. The description proper starts at its first
     * heading, so anything before that is not ours.
     */
    private function stripEchoedLabels(string $description): string
    {
        $clean = trim($description);
        $clean = preg_replace('/<<<DESCRIPTION|DESCRIPTION>>>/', '', $clean) ?? $clean;

        $firstHeading = mb_strpos($clean, '## ');

        if ($firstHeading !== false && $firstHeading > 0) {
            $preamble = mb_substr($clean, 0, $firstHeading);

            // Only cut a preamble that is label noise. Real prose above the
            // first heading is somebody's summary paragraph — keep it.
            if (preg_match('/^\s*(title|description|skills)\s*:/i', $preamble)) {
                $clean = mb_substr($clean, $firstHeading);
            }
        }

        return trim($clean);
    }

    /**
     * Skills stay lowercase and unique so they keep matching the taxonomy the
     * mock and the candidate matcher read from.
     *
     * @param  list<string>  $fallback
     * @return list<string>
     */
    private function normaliseSkills(mixed $skills, array $fallback): array
    {
        if (! is_array($skills)) {
            return $fallback;
        }

        $clean = [];

        foreach ($skills as $skill) {
            if (! is_string($skill)) {
                continue;
            }

            $value = mb_strtolower(trim(preg_replace('/\s+/', ' ', $skill) ?? ''));

            if ($value !== '' && mb_strlen($value) <= 40) {
                $clean[$value] = true;
            }
        }

        // A revision that returns no skills at all is more likely a bad answer
        // than a genuine instruction to remove every one of them.
        return $clean === [] ? $fallback : array_slice(array_keys($clean), 0, 12);
    }
}
