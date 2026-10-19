<?php

declare(strict_types=1);

namespace App\Support\Interviews;

/**
 * Who spoke in an interview recording, from the speech-to-text service's word
 * list (each word carries a speaker label and a time).
 *
 * The recording holds the interviewer's synthetic voice as well as whoever
 * answers, so the interviewer's own speaker is found first, by how much of what
 * that speaker said is the wording of the interviewer's questions. What is left
 * is the answering side: one voice is the candidate; two voices (each with
 * enough speech to matter) means someone else answered some of it.
 *
 * Diarization sometimes splits one person into two labels (a change of
 * microphone position, a cough), so a second voice is reported as POSSIBLE and
 * never decides anything on its own.
 */
final class VoiceTurns
{
    /** A voice with less speech than this is a stray word, not a person. */
    public const MIN_SECS = 8;

    /**
     * @param  list<array{speaker: string, text: string, start: float, end: float}>  $words
     * @param  list<string>  $interviewerLines
     * @return array{voices: int, interviewer: string|null, secs_a: int, secs_b: int, first_b_at: int|null}
     */
    public static function analyse(array $words, array $interviewerLines): array
    {
        $secs = [];
        $tokens = [];
        $first = [];
        foreach ($words as $w) {
            $speaker = $w['speaker'];
            $secs[$speaker] = ($secs[$speaker] ?? 0.0) + max(0.0, $w['end'] - $w['start']);
            $first[$speaker] ??= $w['start'];
            $token = self::token($w['text']);
            if ($token !== '') {
                $tokens[$speaker][] = $token;
            }
        }

        $vocabulary = [];
        foreach ($interviewerLines as $line) {
            foreach (preg_split('/\s+/u', mb_strtolower($line)) ?: [] as $raw) {
                $token = self::token($raw);
                if ($token !== '') {
                    $vocabulary[$token] = true;
                }
            }
        }

        // The interviewer: the speaker whose words are mostly the questions' own words.
        $interviewer = null;
        $best = 0.0;
        foreach ($tokens as $speaker => $list) {
            if (count($list) < 8) {
                continue;
            }
            $share = count(array_filter($list, fn (string $t) => isset($vocabulary[$t]))) / count($list);
            if ($share >= 0.55 && $share > $best) {
                $best = $share;
                $interviewer = (string) $speaker;
            }
        }

        $answering = array_filter($secs, fn (float $s, string|int $speaker) => (string) $speaker !== $interviewer && $s >= self::MIN_SECS, ARRAY_FILTER_USE_BOTH);
        uksort($answering, fn ($a, $b) => $first[$a] <=> $first[$b]);
        $voices = array_values($answering);
        $order = array_keys($answering);

        return [
            'voices' => count($voices),
            'interviewer' => $interviewer,
            'secs_a' => isset($voices[0]) ? (int) round($voices[0]) : 0,
            'secs_b' => isset($voices[1]) ? (int) round($voices[1]) : 0,
            'first_b_at' => isset($order[1]) ? (int) round($first[$order[1]]) : null,
        ];
    }

    private static function token(string $text): string
    {
        $t = preg_replace('/[^\p{L}\p{N}]+/u', '', mb_strtolower($text)) ?? '';

        return mb_strlen($t) >= 3 ? $t : '';
    }
}
