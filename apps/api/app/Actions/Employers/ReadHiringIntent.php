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
 * Turn what an employer said into the fields a JD needs.
 *
 * "hair Taurus can you post job for Java developer a full stack Java developer
 * for" is a real thing somebody dictated. A human reads that and knows the
 * role is Full Stack Java Developer; a regular expression reads it and makes
 * the whole sentence the job title, which is what used to happen.
 *
 * So the model does the reading. It is better at exactly the things that broke
 * the old parser: greetings, the assistant's own name, speech-recognition
 * mangling, a role stated twice, and sentences that trail off mid-thought.
 *
 * It is told to return null rather than guess. Silence about experience or
 * headcount is information — it means the console should ask, and a guessed
 * number would be inherited by the mock interview and by every applicant's
 * score.
 */
final readonly class ReadHiringIntent
{
    public function __construct(private AiGateway $ai) {}

    /**
     * @return array{title: string, experience_min_years: int|null, experience_max_years: int|null, locations: list<string>, openings: int|null, remote: bool}
     */
    public function handle(User $actor, string $said): array
    {
        $said = trim($said);

        if ($said === '') {
            throw ValidationException::withMessages(['said' => 'Nothing was said.']);
        }

        return app(TenantContext::class)->run($actor->tenant, function () use ($actor, $said): array {
            try {
                $result = $this->ai->complete($actor, AiPurpose::JdExtract, 'jd_intent', 1, [
                    'said' => mb_substr($said, 0, 1200),
                ], ['max_tokens' => 400]);

                $parsed = JsonOutput::object($result->text);
            } catch (Throwable) {
                // The console keeps a parser of its own for exactly this. Fail
                // loudly enough for it to notice and fall back, rather than
                // returning a confident empty answer.
                throw ValidationException::withMessages([
                    'said' => 'Could not read that request.',
                ]);
            }

            if (! is_array($parsed)) {
                throw ValidationException::withMessages(['said' => 'Could not read that request.']);
            }

            $min = $this->years($parsed['experience_min_years'] ?? null);
            $max = $this->years($parsed['experience_max_years'] ?? null);

            // "5 to 3 years" is somebody speaking loosely, not a contradiction;
            // the jobs API rejects a maximum below the minimum, and that error
            // would read as our fault rather than a misheard sentence.
            if ($min !== null && $max !== null && $max < $min) {
                [$min, $max] = [$max, $min];
            }

            return [
                'title' => $this->title($parsed['title'] ?? ''),
                'experience_min_years' => $min,
                'experience_max_years' => $max,
                'locations' => $this->locations($parsed['locations'] ?? []),
                'openings' => $this->count($parsed['openings'] ?? null),
                'remote' => (bool) ($parsed['remote'] ?? false),
            ];
        });
    }

    /** A title long enough to be a sentence is the bug this class exists to fix. */
    private function title(mixed $value): string
    {
        if (! is_string($value)) {
            return '';
        }

        $title = trim(preg_replace('/\s+/', ' ', $value) ?? '');

        return mb_strlen($title) > 80 ? '' : $title;
    }

    private function years(mixed $value): ?int
    {
        if (! is_numeric($value)) {
            return null;
        }

        $years = (int) $value;

        // Nobody has 60 years of experience in anything we hire for; a number
        // that large is the model having misread a salary or a phone number.
        return $years >= 0 && $years <= 50 ? $years : null;
    }

    private function count(mixed $value): ?int
    {
        if (! is_numeric($value)) {
            return null;
        }

        $openings = (int) $value;

        return $openings >= 1 && $openings <= 999 ? $openings : null;
    }

    /**
     * @return list<string>
     */
    private function locations(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $places = [];

        foreach ($value as $place) {
            if (! is_string($place)) {
                continue;
            }

            $clean = trim(preg_replace('/\s+/', ' ', $place) ?? '');

            // "remote" is a work mode and has its own flag; letting it through
            // here would file it as a city.
            if ($clean !== '' && mb_strlen($clean) <= 60 && ! preg_match('/^(remote|wfh|work from home|anywhere)$/i', $clean)) {
                $places[] = $clean;
            }
        }

        return array_values(array_slice(array_unique($places), 0, 5));
    }
}
