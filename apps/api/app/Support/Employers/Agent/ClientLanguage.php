<?php

declare(strict_types=1);

namespace App\Support\Employers\Agent;

/**
 * Is the client writing in something other than English? Taurus answers in the
 * language and script the client used (Hindi, Kannada, a Hinglish mix, English),
 * so the question this answers is only "is it worth translating the reply?".
 *
 * Cheap and deliberately cautious: a script that is not Latin (Devanagari,
 * Kannada, Tamil, Telugu, Malayalam, Bengali, Gujarati, Gurmukhi) always counts;
 * Latin text counts only when it carries words that are unmistakably Hindi or
 * Kannada written in English letters. Anything else is treated as English, and
 * English replies are never sent through a translator.
 */
final class ClientLanguage
{
    private const INDIC_SCRIPTS = '/[\x{0900}-\x{097F}\x{0980}-\x{09FF}\x{0A00}-\x{0A7F}\x{0A80}-\x{0AFF}\x{0B80}-\x{0BFF}\x{0C00}-\x{0C7F}\x{0C80}-\x{0CFF}\x{0D00}-\x{0D7F}]/u';

    /** Hindi and Kannada words in English letters that English text does not contain. */
    private const ROMAN_MARKERS = [
        // Hindi
        'hai', 'hain', 'kya', 'karo', 'kariye', 'kijiye', 'kijiyega', 'bhejo', 'bhejiye', 'bhej', 'haan', 'nahi', 'nahin', 'aap', 'aapko',
        'mujhe', 'mera', 'meri', 'chahiye', 'dobara', 'dubara', 'abhi', 'kitne', 'kaun', 'kaunsa', 'batao', 'bataiye', 'dikhao', 'dikhaiye',
        'kaise', 'rahe', 'raha', 'rahi', 'hoon', 'apka', 'aapka', 'suno', 'sunao', 'tumhara', 'kripya', 'shukriya', 'dhanyavaad', 'theek', 'thik', 'accha', 'achha', 'sabhi', 'karna', 'dena', 'dijiye', 'kab', 'kyun', 'wala', 'wali',
        // Kannada
        'illa', 'houdu', 'haudu', 'nanage', 'beku', 'beda', 'maadi', 'madi', 'kalisi', 'helu', 'heli', 'yaaru', 'yeshtu', 'eega', 'matte',
        'mattomme', 'yenu', 'nimma', 'namma', 'banni', 'swalpa', 'agali', 'ide', 'idhe', 'torisi', 'kodi',
    ];

    /** Kannada words in English letters (a subset of ROMAN_MARKERS, to tell Kannada from Hindi). */
    private const KANNADA_ROMAN = [
        'illa', 'houdu', 'haudu', 'nanage', 'beku', 'beda', 'maadi', 'madi', 'kalisi', 'helu', 'heli', 'yaaru', 'yeshtu', 'eega', 'matte',
        'mattomme', 'yenu', 'nimma', 'namma', 'banni', 'swalpa', 'agali', 'torisi', 'kodi',
    ];

    /** Script ranges and what to call the language written in them. */
    private const SCRIPTS = [
        ['/[\x{0900}-\x{097F}]/u', 'Hindi, written in Devanagari script'],
        ['/[\x{0C80}-\x{0CFF}]/u', 'Kannada, written in Kannada script'],
        ['/[\x{0B80}-\x{0BFF}]/u', 'Tamil, written in Tamil script'],
        ['/[\x{0C00}-\x{0C7F}]/u', 'Telugu, written in Telugu script'],
        ['/[\x{0D00}-\x{0D7F}]/u', 'Malayalam, written in Malayalam script'],
        ['/[\x{0980}-\x{09FF}]/u', 'Bengali, written in Bengali script'],
        ['/[\x{0A80}-\x{0AFF}]/u', 'Gujarati, written in Gujarati script'],
        ['/[\x{0A00}-\x{0A7F}]/u', 'Punjabi, written in Gurmukhi script'],
    ];

    /**
     * What the client wrote in, named for the translator, so the model never needs to see their words (it
     * once copied a name from the message into the reply). Null when it is English.
     */
    public static function describe(string $text): ?string
    {
        foreach (self::SCRIPTS as [$pattern, $name]) {
            if (preg_match($pattern, $text) === 1) {
                return $name;
            }
        }

        $words = preg_split('/[^a-z]+/', mb_strtolower($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if (array_intersect($words, self::KANNADA_ROMAN) !== []) {
            return 'Kannada, written in English letters';
        }

        return array_intersect($words, self::ROMAN_MARKERS) !== [] ? 'Hindi, written in English letters (Hinglish)' : null;
    }

    public static function needsLocalizing(string $text): bool
    {
        $text = trim($text);
        if ($text === '') {
            return false;
        }
        if (preg_match(self::INDIC_SCRIPTS, $text) === 1) {
            return true;
        }

        $words = preg_split('/[^a-z]+/', mb_strtolower($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return count(array_intersect($words, self::ROMAN_MARKERS)) >= 1;
    }
}
