<?php

declare(strict_types=1);

namespace App\Support\Messaging;

/**
 * Blocks messages/templates containing never-claim phrases (PRD §6.9 + §14.3:
 * no "guaranteed job", income claims). Protects the WABA and radical-honesty
 * compliance. Case-insensitive substring match against config.
 */
final class BannedPhraseLinter
{
    /**
     * @return list<string> the banned phrases found (empty == clean)
     */
    public function violations(string $text): array
    {
        $haystack = mb_strtolower($text);
        $found = [];

        foreach ((array) config('messaging.banned_phrases', []) as $phrase) {
            if ($phrase !== '' && str_contains($haystack, mb_strtolower((string) $phrase))) {
                $found[] = (string) $phrase;
            }
        }

        return $found;
    }

    public function passes(string $text): bool
    {
        return $this->violations($text) === [];
    }
}
