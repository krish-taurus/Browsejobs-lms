<?php

declare(strict_types=1);

namespace App\Support\Import;

use RuntimeException;
use SimpleXMLElement;
use ZipArchive;

/**
 * Minimal, dependency-free reader for the first worksheet of an .xlsx file.
 * An .xlsx is a zip of XML parts; for a text grid (like the syllabus template)
 * we only need the shared-string table and the first sheet, so we read those
 * two parts directly rather than pulling in a full spreadsheet library.
 *
 * Scope on purpose: strings, numbers-as-text, and the first sheet only. It does
 * not interpret formulas, styles, or date number-formats — none of which the
 * syllabus import needs.
 */
final class XlsxReader
{
    /**
     * Read the first worksheet as a grid of trimmed string cells. Ragged rows
     * are padded so every returned row is a contiguous 0-indexed list.
     *
     * @return list<list<string>>
     *
     * @throws RuntimeException when the file is not a readable .xlsx
     */
    public static function rows(string $path): array
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            throw new RuntimeException('The file could not be opened as an Excel workbook.');
        }

        try {
            $shared = self::sharedStrings($zip);
            $sheet = self::firstSheetXml($zip);
        } finally {
            $zip->close();
        }

        $xml = self::parseXml($sheet);
        if ($xml->sheetData === null) {
            return [];
        }

        $rows = [];
        foreach ($xml->sheetData->row as $row) {
            $cells = [];
            foreach ($row->c as $c) {
                $col = self::columnIndex((string) $c['r']);
                $cells[$col] = self::cellValue($c, $shared);
            }

            if ($cells === []) {
                $rows[] = [];

                continue;
            }

            $width = max(array_keys($cells));
            $line = [];
            for ($i = 0; $i <= $width; $i++) {
                $line[] = trim($cells[$i] ?? '');
            }
            $rows[] = $line;
        }

        return $rows;
    }

    /** @return list<string> the shared-string table (empty when absent). */
    private static function sharedStrings(ZipArchive $zip): array
    {
        $raw = $zip->getFromName('xl/sharedStrings.xml');
        if ($raw === false) {
            return [];
        }

        $strings = [];
        foreach (self::parseXml($raw)->si as $si) {
            $strings[] = self::richText($si);
        }

        return $strings;
    }

    /** The first worksheet's XML, tolerating a non-standard sheet filename. */
    private static function firstSheetXml(ZipArchive $zip): string
    {
        $raw = $zip->getFromName('xl/worksheets/sheet1.xml');
        if ($raw !== false) {
            return $raw;
        }

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if ($name !== false && str_starts_with($name, 'xl/worksheets/') && str_ends_with($name, '.xml')) {
                $raw = $zip->getFromName($name);
                if ($raw !== false) {
                    return $raw;
                }
            }
        }

        throw new RuntimeException('The workbook has no readable worksheet.');
    }

    /** @param  list<string>  $shared */
    private static function cellValue(SimpleXMLElement $c, array $shared): string
    {
        $type = (string) $c['t'];

        if ($type === 's') {
            $idx = (int) $c->v;

            return $shared[$idx] ?? '';
        }

        if ($type === 'inlineStr') {
            return $c->is !== null ? self::richText($c->is) : '';
        }

        return (string) $c->v;
    }

    /** Flatten a shared-string / inline-string node (plain <t> or rich <r><t>). */
    private static function richText(SimpleXMLElement $node): string
    {
        if (isset($node->t)) {
            return (string) $node->t;
        }

        $text = '';
        foreach ($node->r as $run) {
            $text .= (string) $run->t;
        }

        return $text;
    }

    /** "B12" → 1 (0-based column index). */
    private static function columnIndex(string $ref): int
    {
        if (! preg_match('/^([A-Z]+)/', $ref, $m)) {
            return 0;
        }

        $n = 0;
        foreach (str_split($m[1]) as $ch) {
            $n = $n * 26 + (ord($ch) - 64);
        }

        return $n - 1;
    }

    private static function parseXml(string $raw): SimpleXMLElement
    {
        $xml = simplexml_load_string($raw);
        if ($xml === false) {
            throw new RuntimeException('The workbook XML could not be parsed.');
        }

        return $xml;
    }
}
