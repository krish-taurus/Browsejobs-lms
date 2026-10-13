<?php

declare(strict_types=1);

namespace App\Support\Employers\Agent;

/**
 * The yes and no of a hiring conversation — typed, said in a voice note, or
 * spoken to the web console (where speech recognition adds its own noise).
 * A short reply that starts with yes is a yes ("yes send them", "haan ji");
 * anything long is read properly instead, because "yes but only the top 3"
 * is a new instruction, not a confirmation.
 */
final class AgentWords
{
    private const YES = [
        'yes', 'y', 'yeah', 'yep', 'yup', 'sure', 'ok', 'okay', 'k', 'confirm', 'confirmed', 'go', 'go ahead', 'proceed',
        'do it', 'send', 'send it', 'send them', 'please do', 'yes please', 'haan', 'han', 'ha', 'haa', 'ji', 'haan ji', 'done', 'approve', 'approved', '👍',
    ];

    private const YES_LEADS = ['yes', 'yeah', 'yep', 'sure', 'ok', 'okay', 'haan', 'go', 'proceed', 'confirm'];

    private const NO = [
        'no', 'n', 'nope', 'nah', 'not now', 'hold', 'hold it', 'wait', 'cancel', 'stop', 'dont', 'do not', 'nahi', 'na', 'later', 'not yet', 'no thanks', 'skip',
    ];

    private const FILLER = ['please', 'pls', 'taurus', 'thanks', 'thank you', 'go ahead'];

    public static function normalize(string $text): string
    {
        return trim(preg_replace('/\s+/', ' ', preg_replace('/[^\p{L}\p{N}\s👍]+/u', '', mb_strtolower($text)) ?? '') ?? '');
    }

    public static function isYes(string $text): bool
    {
        $w = self::normalize($text);
        if (in_array($w, self::YES, true) || in_array(self::strip($w), self::YES, true)) {
            return true;
        }

        $words = explode(' ', $w);

        return count($words) <= 4 && in_array($words[0], self::YES_LEADS, true) && ! self::hasQualifier($w);
    }

    public static function isNo(string $text): bool
    {
        $w = self::normalize($text);

        return in_array($w, self::NO, true) || in_array(self::strip($w), self::NO, true);
    }

    private static function strip(string $w): string
    {
        foreach (self::FILLER as $f) {
            $w = trim(preg_replace('/\b'.preg_quote($f, '/').'\b/u', '', $w) ?? $w);
        }

        return trim(preg_replace('/\s+/', ' ', $w) ?? $w);
    }

    /** "yes but only…", "ok just the top 2" carry a new instruction. */
    private static function hasQualifier(string $w): bool
    {
        return preg_match('/\b(but|only|just|except|top|instead|not|without)\b/u', $w) === 1;
    }
}
