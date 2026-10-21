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
        // Hindi, in English letters and in Devanagari
        'ji haan', 'theek hai', 'thik hai', 'theek', 'sahi hai', 'kar do', 'kardo', 'bhej do', 'bhejdo', 'bhejo', 'ho jaye', 'हाँ', 'हां', 'जी', 'जी हाँ', 'जी हां', 'हाँ जी', 'हां जी', 'ठीक है', 'सही है', 'कर दो', 'भेज दो', 'भेजो',
        // Kannada, in English letters and in Kannada script
        'houdu', 'haudu', 'sari', 'agali', 'ayitu', 'maadi', 'madi', 'kalisi', 'ಹೌದು', 'ಸರಿ', 'ಆಯ್ತು', 'ಆಗಲಿ', 'ಮಾಡಿ', 'ಕಳುಹಿಸಿ',
    ];

    private const YES_LEADS = ['yes', 'yeah', 'yep', 'sure', 'ok', 'okay', 'haan', 'go', 'proceed', 'confirm', 'houdu', 'haudu', 'sari', 'हाँ', 'हां', 'जी', 'ठीक', 'ಹೌದು', 'ಸರಿ'];

    private const NO = [
        'no', 'n', 'nope', 'nah', 'not now', 'hold', 'hold it', 'wait', 'cancel', 'stop', 'dont', 'do not', 'nahi', 'na', 'later', 'not yet', 'no thanks', 'skip',
        'nahin', 'mat karo', 'ruko', 'ruk jao', 'rehne do', 'abhi nahi', 'नहीं', 'ना', 'मत करो', 'रुको', 'रुक जाओ', 'रहने दो', 'अभी नहीं',
        'beda', 'illa', 'nillisi', 'ಬೇಡ', 'ಇಲ್ಲ', 'ಇಲ್ಲಾ', 'ನಿಲ್ಲಿಸಿ',
    ];

    private const FILLER = ['please', 'pls', 'taurus', 'thanks', 'thank you', 'go ahead'];

    public static function normalize(string $text): string
    {
        return trim(preg_replace('/\s+/', ' ', preg_replace('/[^\p{L}\p{M}\p{N}\s👍]+/u', '', mb_strtolower($text)) ?? '') ?? '');
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
        return preg_match('/(?<![\p{L}\p{M}\p{N}])(but|only|just|except|top|instead|not|without|lekin|magar|sirf|bas|adre|सिर्फ|केवल|लेकिन|मगर|बस|ಆದರೆ|ಮಾತ್ರ)(?![\p{L}\p{M}\p{N}])/u', $w) === 1;
    }
}
