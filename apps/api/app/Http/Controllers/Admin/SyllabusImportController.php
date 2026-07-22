<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Curriculum\ImportSyllabusCsv;
use App\Http\Controllers\Controller;
use App\Support\Import\XlsxReader;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Bulk syllabus authoring: download the template, fill it in a spreadsheet,
 * upload it (CSV or Excel .xlsx), and the whole Program → Course → Module →
 * Topic → Lesson tree is built in one shot (every topic scaffolded with Quiz +
 * Assignment + Mock). Behind can:manage-curriculum.
 */
final class SyllabusImportController extends Controller
{
    private const HEADERS = ['program', 'course', 'module', 'topic', 'lesson_title', 'lesson_type'];

    private const REQUIRED = ['program', 'course', 'module', 'topic'];

    /** A ready-to-fill CSV with a couple of example rows. */
    public function template(): Response
    {
        $rows = [
            self::HEADERS,
            ['Data Engineering', 'DE Bootcamp', 'ETL Foundations', 'Batch vs Streaming', 'Intro to ETL', 'notes'],
            ['Data Engineering', 'DE Bootcamp', 'ETL Foundations', 'Batch vs Streaming', 'ETL walkthrough', 'video'],
            ['Data Engineering', 'DE Bootcamp', 'Spark', 'RDDs & DataFrames', 'Spark basics', 'notes'],
            ['Data Analytics', 'DA Bootcamp', 'SQL', 'Joins & Windows', 'SQL for analysts', 'notes'],
        ];

        $out = fopen('php://temp', 'r+');
        foreach ($rows as $r) {
            fputcsv($out, $r);
        }
        rewind($out);
        $csv = (string) stream_get_contents($out);
        fclose($out);

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="browsejobs-syllabus-template.csv"',
        ]);
    }

    public function import(Request $request, ImportSyllabusCsv $import): JsonResponse
    {
        // Excel .xlsx is a zip, so finfo reports it as application/zip — the `mimes`
        // rule is unreliable for it. We validate it's an uploaded file, then choose
        // the parser by extension and let a bad file surface a friendly parse error.
        $request->validate([
            'file' => ['required', 'file', 'max:4096'],
        ]);

        $file = $request->file('file');
        $isXlsx = strtolower((string) $file->getClientOriginalExtension()) === 'xlsx';

        $grid = $isXlsx ? $this->readXlsx($file->getRealPath()) : $this->readCsv($file->getRealPath());
        $rows = $this->gridToRows($grid);

        $tenant = app(TenantContext::class)->get();
        $summary = $import->handle($tenant, $rows);

        return response()->json(['data' => $summary], 201);
    }

    /**
     * Read a CSV into a raw grid of string cells (header row first).
     *
     * @return list<list<string>>
     */
    private function readCsv(string $path): array
    {
        $handle = fopen($path, 'r');
        if ($handle === false) {
            throw ValidationException::withMessages(['file' => 'Could not read the file.']);
        }

        $grid = [];
        while (($data = fgetcsv($handle)) !== false) {
            $grid[] = array_map(fn ($v) => (string) $v, $data);
        }
        fclose($handle);

        return $grid;
    }

    /**
     * Read the first worksheet of an .xlsx into a raw grid of string cells.
     *
     * @return list<list<string>>
     */
    private function readXlsx(string $path): array
    {
        try {
            return XlsxReader::rows($path);
        } catch (Throwable $e) {
            throw ValidationException::withMessages(['file' => $e->getMessage()]);
        }
    }

    /**
     * Turn a raw grid (header row + data rows) into header-keyed rows. Header
     * names are lower-cased and trimmed so column order and casing don't matter;
     * unknown columns are ignored and blank lines skipped.
     *
     * @param  list<list<string>>  $grid
     * @return list<array<string, string>>
     */
    private function gridToRows(array $grid): array
    {
        if ($grid === []) {
            throw ValidationException::withMessages(['file' => 'The file is empty.']);
        }

        $header = array_map(fn ($h) => strtolower(trim((string) $h)), array_shift($grid));

        $missing = array_diff(self::REQUIRED, $header);
        if ($missing !== []) {
            throw ValidationException::withMessages([
                'file' => 'Missing required columns: '.implode(', ', $missing).'. Download the template.',
            ]);
        }

        $rows = [];
        foreach ($grid as $data) {
            if (implode('', array_map('strval', $data)) === '') {
                continue; // skip blank lines
            }
            $row = [];
            foreach ($header as $i => $col) {
                $row[$col] = isset($data[$i]) ? (string) $data[$i] : '';
            }
            $rows[] = $row;
        }

        return $rows;
    }
}
