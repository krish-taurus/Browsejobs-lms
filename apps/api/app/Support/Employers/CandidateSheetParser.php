<?php

declare(strict_types=1);

namespace App\Support\Employers;

use App\Support\Import\XlsxReader;
use RuntimeException;

/**
 * Reads a client's candidate sheet — their own database export or a Naukri
 * (Resdex / RMS) Excel download — into plain candidate rows.
 *
 * Column names differ between exports, so the header row is found by
 * recognising known column names ("Key Skills", "Mobile No.", "Total
 * Experience", …) rather than by position. Anything unrecognised is ignored.
 * .xlsx and .csv only: an old .xls must be re-saved as .xlsx first.
 */
final class CandidateSheetParser
{
    /** Normalised header text → our field. First match wins. */
    private const HEADERS = [
        'name' => ['name', 'full name', 'candidate name', 'candidate', 'applicant name', 'name of the candidate'],
        'email' => ['email', 'email id', 'e mail', 'e mail id', 'email address', 'mail id', 'email id s', 'primary email'],
        'phone' => ['phone', 'mobile', 'mobile no', 'mobile number', 'phone number', 'contact', 'contact number', 'contact no', 'mobile no s', 'phone no', 'whatsapp', 'whatsapp number'],
        'current_title' => ['current designation', 'designation', 'current title', 'job title', 'title', 'current role', 'role', 'position'],
        'current_company' => ['current company', 'company', 'current employer', 'employer', 'organization', 'organisation', 'current company name'],
        'location' => ['current location', 'location', 'city', 'current city'],
        'experience' => ['total experience', 'experience', 'work experience', 'total exp', 'exp', 'years of experience', 'experience years', 'experience in years', 'total work experience'],
        'skills' => ['key skills', 'skills', 'skill set', 'skillset', 'it skills', 'primary skills', 'technical skills', 'core skills'],
        'notice_period' => ['notice period', 'notice', 'notice period days', 'availability to join'],
        'current_ctc' => ['current ctc', 'annual salary', 'current salary', 'ctc', 'current annual salary'],
        'expected_ctc' => ['expected ctc', 'expected salary', 'expected annual salary'],
        'summary' => ['resume headline', 'headline', 'summary', 'profile summary', 'resume title', 'profile'],
        'education' => ['education', 'highest qualification', 'qualification', 'highest education', 'ug', 'pg', 'degree'],
    ];

    private const MAX_ROWS = 5000;

    /**
     * @return list<array{row: int, name: string, email: ?string, phone: ?string, current_title: ?string, current_company: ?string, location: ?string, experience_years: ?float, notice_period: ?string, current_ctc: ?string, expected_ctc: ?string, education: ?string, skills: list<string>, summary: ?string}>
     *
     * @throws RuntimeException when the file cannot be read or has no recognisable header
     */
    public function parse(string $path, string $extension): array
    {
        $grid = match (strtolower($extension)) {
            'xlsx' => XlsxReader::rows($path),
            'csv', 'txt' => $this->csvRows($path),
            default => throw new RuntimeException('Upload an Excel (.xlsx) or CSV file. An old .xls file must be saved as .xlsx first.'),
        };

        [$headerIndex, $map] = $this->findHeader($grid);
        if ($headerIndex === null) {
            throw new RuntimeException('Could not find a header row with columns like Name, Mobile/Email and Key Skills.');
        }

        $rows = [];
        foreach (array_slice($grid, $headerIndex + 1, self::MAX_ROWS, true) as $i => $cells) {
            $get = fn (string $field): string => isset($map[$field]) ? trim((string) ($cells[$map[$field]] ?? '')) : '';

            if (implode('', array_map('trim', $cells)) === '') {
                continue;
            }

            $rows[] = [
                'row' => $i + 1,
                'name' => $this->clip($get('name'), 255) ?? '',
                'email' => $this->email($get('email')),
                'phone' => $this->phone($get('phone')),
                'current_title' => $this->clip($get('current_title'), 255),
                'current_company' => $this->clip($get('current_company'), 255),
                'location' => $this->clip($get('location'), 255),
                'experience_years' => $this->years($get('experience')),
                'notice_period' => $this->clip($get('notice_period'), 60),
                'current_ctc' => $this->clip($get('current_ctc'), 60),
                'expected_ctc' => $this->clip($get('expected_ctc'), 60),
                'education' => $this->clip($get('education'), 255),
                'skills' => $this->skills($get('skills')),
                'summary' => $this->clip($get('summary'), 2000),
            ];
        }

        return $rows;
    }

    /**
     * @param  list<list<string>>  $grid
     * @return array{0: int|null, 1: array<string, int>}
     */
    private function findHeader(array $grid): array
    {
        foreach (array_slice($grid, 0, 15, true) as $index => $cells) {
            $map = [];
            foreach ($cells as $col => $cell) {
                $label = $this->normaliseHeader((string) $cell);
                if ($label === '') {
                    continue;
                }
                foreach (self::HEADERS as $field => $names) {
                    if (! isset($map[$field]) && in_array($label, $names, true)) {
                        $map[$field] = $col;
                        break;
                    }
                }
            }

            // A real header names the person and some way to reach or match them.
            if (isset($map['name']) && (isset($map['phone']) || isset($map['email']) || isset($map['skills']))) {
                return [$index, $map];
            }
        }

        return [null, []];
    }

    /** @return list<list<string>> */
    private function csvRows(string $path): array
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new RuntimeException('The file could not be opened.');
        }

        $rows = [];
        try {
            while (($row = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
                $rows[] = array_map(fn ($v) => trim((string) $v), $row);
                if (count($rows) > self::MAX_ROWS + 20) {
                    break;
                }
            }
        } finally {
            fclose($handle);
        }

        if (isset($rows[0][0])) {
            $rows[0][0] = preg_replace('/^\xEF\xBB\xBF/', '', $rows[0][0]) ?? $rows[0][0]; // UTF-8 BOM from Excel
        }

        return $rows;
    }

    private function normaliseHeader(string $text): string
    {
        $text = mb_strtolower(trim($text));
        $text = preg_replace('/\(.*?\)/', ' ', $text) ?? $text; // "Notice Period (days)" → "notice period"
        $text = preg_replace('/[^a-z0-9]+/', ' ', $text) ?? $text;

        return trim($text);
    }

    /**
     * "5 Year(s) 3 Month(s)", "5y 3m", "5.5", "3-5 yrs", "Fresher" → years.
     */
    private function years(string $raw): ?float
    {
        $text = mb_strtolower(trim($raw));
        if ($text === '') {
            return null;
        }
        if (str_contains($text, 'fresher')) {
            return 0.0;
        }

        $years = preg_match('/(\d+(?:\.\d+)?)\s*(?:y|yr|yrs|year|years)/', $text, $y) ? (float) $y[1] : null;
        $months = preg_match('/(\d+)\s*(?:m|mo|mos|month|months)\b/', $text, $m) ? (int) $m[1] : 0;

        if ($years === null && preg_match('/^(\d+(?:\.\d+)?)(?:\s*[-–]\s*\d+(?:\.\d+)?)?/', $text, $n)) {
            $years = (float) $n[1]; // a bare number, or the low end of a range
        }

        if ($years === null && $months === 0) {
            return null;
        }

        return min(60.0, round(($years ?? 0.0) + $months / 12, 1));
    }

    /** @return list<string> */
    private function skills(string $raw): array
    {
        $parts = preg_split('/[,;|\n\/]+/', $raw) ?: [];
        $out = [];
        foreach ($parts as $part) {
            $skill = trim($part, " \t\r\n.-•");
            if ($skill !== '' && mb_strlen($skill) <= 60) {
                $out[mb_strtolower($skill)] = $skill;
            }
        }

        return array_slice(array_values($out), 0, 80);
    }

    private function email(string $raw): ?string
    {
        // A cell can hold several addresses; keep the first valid one.
        foreach (preg_split('/[\s,;]+/', $raw) ?: [] as $part) {
            if (filter_var($part, FILTER_VALIDATE_EMAIL)) {
                return mb_strtolower($part);
            }
        }

        return null;
    }

    private function phone(string $raw): ?string
    {
        // First number in the cell; Excel may also have turned it into "9.19876E+11".
        $first = preg_split('/[,;\/]|\s{2,}/', $raw)[0] ?? '';
        if (preg_match('/^\d+(\.\d+)?e\+\d+$/i', trim($first))) {
            $first = number_format((float) $first, 0, '', '');
        }
        $digits = preg_replace('/\D+/', '', $first) ?? '';
        if (strlen($digits) === 12 && str_starts_with($digits, '91')) {
            $digits = substr($digits, 2);
        }
        if (strlen($digits) === 11 && str_starts_with($digits, '0')) {
            $digits = substr($digits, 1);
        }

        return strlen($digits) >= 10 && strlen($digits) <= 15 ? $digits : null;
    }

    private function clip(string $value, int $max): ?string
    {
        $value = trim($value);

        return $value === '' ? null : mb_substr($value, 0, $max);
    }
}
