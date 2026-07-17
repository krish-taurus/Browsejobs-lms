<?php

declare(strict_types=1);

namespace App\Support\Content;

/**
 * Normalizes a pasted/uploaded transcript to clean plain text (PRD §6.10) so the
 * downstream notes + KB chunks stay coherent. Strips WebVTT cue timings/ids/headers
 * and inline tags; collapses blank lines; caps length.
 */
final class TranscriptCleaner
{
    public function clean(string $raw): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $raw);

        // WebVTT (.vtt): drop the header, NOTE/STYLE blocks, cue-timing lines, numeric
        // cue ids, and inline <...> tags — leaving just the spoken text.
        if (str_starts_with(ltrim($text), 'WEBVTT') || str_contains($text, '-->')) {
            $lines = [];
            foreach (explode("\n", $text) as $line) {
                $trimmed = trim($line);
                if ($trimmed === '' || str_starts_with($trimmed, 'WEBVTT') || str_starts_with($trimmed, 'NOTE') || str_starts_with($trimmed, 'STYLE')) {
                    $lines[] = '';

                    continue;
                }
                if (str_contains($trimmed, '-->') || preg_match('/^\d+$/', $trimmed) === 1) {
                    continue; // cue timing or numeric cue id
                }
                $lines[] = preg_replace('/<[^>]+>/', '', $trimmed) ?? $trimmed;
            }
            $text = implode("\n", $lines);
        }

        // Collapse 3+ newlines to a paragraph break; trim; strip control chars.
        $text = preg_replace('/\n{3,}/', "\n\n", trim($text)) ?? '';
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $text) ?? '';

        return mb_substr($text, 0, (int) config('content.max_transcript_chars', 60000));
    }
}
