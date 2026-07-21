<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Curriculum\ImportSyllabusCsv;
use App\Http\Controllers\Controller;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

/**
 * Bulk syllabus authoring: download a CSV template, fill it in a spreadsheet,
 * upload it, and the whole Program → Course → Module → Topic → Lesson tree is
 * built in one shot (every topic scaffolded with Quiz + Assignment + Mock).
 * Behind can:manage-curriculum.
 */
final class SyllabusImportController extends Controller
{
    private const HEADERS = ['program', 'course', 'module', 'topic', 'lesson_title', 'lesson_type'];

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
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
        ]);

        $rows = $this->parse($request->file('file')->getRealPath());
        $tenant = app(TenantContext::class)->get();
        $summary = $import->handle($tenant, $rows);

        return response()->json(['data' => $summary], 201);
    }

    /**
     * Parse the CSV into header-keyed rows. Header names are lower-cased and
     * trimmed so column order and casing don't matter; unknown columns ignored.
     *
     * @return list<array<string, string>>
     */
    private function parse(string $path): array
    {
        $handle = fopen($path, 'r');
        if ($handle === false) {
            throw ValidationException::withMessages(['file' => 'Could not read the file.']);
        }

        $header = fgetcsv($handle);
        if ($header === false || $header === null) {
            fclose($handle);
            throw ValidationException::withMessages(['file' => 'The file is empty.']);
        }
        $header = array_map(fn ($h) => strtolower(trim((string) $h)), $header);

        $missing = array_diff(['program', 'course', 'module', 'topic'], $header);
        if ($missing !== []) {
            fclose($handle);
            throw ValidationException::withMessages([
                'file' => 'Missing required columns: '.implode(', ', $missing).'. Download the template.',
            ]);
        }

        $rows = [];
        while (($data = fgetcsv($handle)) !== false) {
            if ($data === [null] || implode('', array_map('strval', $data)) === '') {
                continue; // skip blank lines
            }
            $row = [];
            foreach ($header as $i => $col) {
                $row[$col] = isset($data[$i]) ? (string) $data[$i] : '';
            }
            $rows[] = $row;
        }
        fclose($handle);

        return $rows;
    }
}
