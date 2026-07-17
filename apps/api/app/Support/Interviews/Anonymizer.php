<?php

declare(strict_types=1);

namespace App\Support\Interviews;

/**
 * Mandatory anonymisation stage (PRD §6.6 — non-negotiable). Deterministic
 * PII scrub applied to every transcript BEFORE the AI parser sees it, and
 * again to every parsed field before bank entry (defence in depth — the
 * parse prompt also instructs name redaction, but a regex never hallucinates).
 */
final class Anonymizer
{
    public function scrub(string $text): string
    {
        // Email addresses.
        $text = (string) preg_replace('/[\w.+-]+@[\w-]+\.[\w.]+/u', '[EMAIL]', $text);

        // Phone numbers: international (+91 98765 43210), separated groups, or
        // any 8+ digit run — long enough to spare years/versions/counts.
        $text = (string) preg_replace('/\+\d[\d\s().-]{7,}\d/', '[PHONE]', $text);
        $text = (string) preg_replace('/\b\d{5}[\s-]?\d{5}\b/', '[PHONE]', $text);
        $text = (string) preg_replace('/\b\d{8,}\b/', '[PHONE]', $text);

        // URLs and social handles — LinkedIn/GitHub links identify people.
        $text = (string) preg_replace('~https?://\S+~', '[LINK]', $text);
        $text = (string) preg_replace('/(?<=\s|^)@[\w.]{3,}/u', '[HANDLE]', $text);

        return $text;
    }

    /**
     * Scrub every string field of a parsed record, recursively.
     *
     * @param  array<string, mixed>  $record
     * @return array<string, mixed>
     */
    public function scrubRecord(array $record): array
    {
        array_walk_recursive($record, function (&$value): void {
            if (is_string($value)) {
                $value = $this->scrub($value);
            }
        });

        return $record;
    }
}
