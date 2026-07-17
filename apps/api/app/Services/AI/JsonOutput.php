<?php

declare(strict_types=1);

namespace App\Services\AI;

/**
 * Extracts a JSON object from a model's reply (ADR 0025).
 *
 * Models wrap JSON in prose or markdown fences no matter how firmly the prompt says
 * not to, so every consumer before this hand-rolled the same brace-substring +
 * `json_decode` dance privately: `GenerateQuiz::parse`, `GradeSubmission::parse`, and
 * three more. Triage reads four fields out of one call and was going to be the sixth
 * copy; this is that logic, once, with tests of its own.
 *
 * Deliberately *not* a schema validator. The gateway stays text-in/text-out and each
 * consumer keeps its own domain validation (a rubric score clamp is not a JSON
 * concern). This owns exactly one job: text → array, or null.
 *
 * Existing consumers are **not** migrated onto it — that refactor is out of scope and
 * would put five working, individually-tested parsers at risk for a cosmetic win.
 */
final class JsonOutput
{
    /**
     * The first JSON object in the text, or null if there isn't a decodable one.
     *
     * Null means "the model did not answer in the shape we asked for", and every caller
     * treats that as "degrade to manual" — the house rule for malformed AI output.
     *
     * @return array<string, mixed>|null
     */
    public static function object(string $text): ?array
    {
        $start = strpos($text, '{');
        $end = strrpos($text, '}');

        if ($start === false || $end === false || $end <= $start) {
            return null;
        }

        $decoded = json_decode(substr($text, $start, $end - $start + 1), true);

        // A JSON scalar or list is not the object shape the caller asked for.
        return is_array($decoded) && ! array_is_list($decoded) ? $decoded : null;
    }

    /** A trimmed string field, or the default when absent/non-scalar. */
    public static function string(array $data, string $key, string $default = ''): string
    {
        $value = $data[$key] ?? null;

        return is_string($value) || is_int($value) ? trim((string) $value) : $default;
    }

    /**
     * A positive integer field, or null. Used for ids, where 0/false/"none"/garbage all
     * have to collapse to "no value" rather than to a row that exists.
     */
    public static function positiveInt(array $data, string $key): ?int
    {
        $value = $data[$key] ?? null;

        if (is_int($value)) {
            return $value > 0 ? $value : null;
        }

        if (is_string($value) && ctype_digit(trim($value))) {
            $int = (int) trim($value);

            return $int > 0 ? $int : null;
        }

        return null;
    }
}
