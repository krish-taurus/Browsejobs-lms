<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Market\ImportMarketJds;
use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\MarketJd;
use App\Support\Market\SkillDemand;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Job-market JD ingestion + demand trends (PRD §6.21). Placement team only.
 * Manual single-entry, CSV bulk import, and a per-course skill-demand view that
 * feeds the syllabus-recommendation engine. Nothing here is scraped.
 */
final class MarketJdController extends Controller
{
    public function __construct(private readonly SkillDemand $demand) {}

    public function index(Request $request): JsonResponse
    {
        $courseId = $request->integer('course_id') ?: null;
        $days = min(365, max(7, $request->integer('days') ?: 180));

        $recent = MarketJd::query()
            ->when($courseId !== null, fn ($q) => $q->where('course_id', $courseId))
            ->with('course:id,name')
            ->orderByDesc('ingested_at')
            ->limit(50)
            ->get()
            ->map(fn (MarketJd $jd) => [
                'id' => $jd->id,
                'role_title' => $jd->role_title,
                'company' => $jd->company,
                'location' => $jd->location,
                'seniority' => $jd->seniority,
                'source' => $jd->source,
                'course' => $jd->course?->name,
                'skills' => $jd->extracted_skills ?? [],
                'parse_status' => $jd->parse_status,
                'quality_score' => $jd->quality_score,
                'ingested_at' => $jd->ingested_at?->toIso8601String(),
            ]);

        return response()->json([
            'data' => [
                'courses' => Course::query()->orderBy('name')->get(['id', 'name']),
                'trends' => $this->demand->forCourse($courseId, $days),
                'sample_size' => $this->demand->sampleSize($courseId, $days),
                'window_days' => $days,
                'recent' => $recent,
            ],
        ]);
    }

    public function store(Request $request, ImportMarketJds $import): JsonResponse
    {
        $data = $request->validate([
            'role_title' => ['required', 'string', 'max:190'],
            'raw_jd' => ['required', 'string', 'min:40', 'max:20000'],
            'company' => ['nullable', 'string', 'max:190'],
            'location' => ['nullable', 'string', 'max:120'],
            'seniority' => ['nullable', 'string', 'in:fresher,junior,mid,senior'],
            'source' => ['nullable', 'string', 'in:manual,csv,partner,api'],
            'course_id' => ['nullable', 'integer'],
        ]);

        if (isset($data['course_id'])) {
            Course::query()->findOrFail((int) $data['course_id']); // tenant-scoped ownership check
        }

        $summary = $import->handle($request->user(), [$data + ['source' => $data['source'] ?? 'manual']]);

        return response()->json(['data' => $summary], 201);
    }

    public function import(Request $request, ImportMarketJds $import): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
            'course_id' => ['nullable', 'integer'],
        ]);

        $courseId = $request->integer('course_id') ?: null;
        if ($courseId !== null) {
            Course::query()->findOrFail($courseId);
        }

        $rows = $this->parseCsv((string) $request->file('file')->getRealPath(), $courseId);
        if ($rows === []) {
            return response()->json([
                'error' => ['code' => 'empty_import', 'message' => 'No usable rows found. Expected columns: role_title, raw_jd (+ optional company, location, seniority).'],
            ], 422);
        }

        $summary = $import->handle($request->user(), $rows);

        return response()->json(['data' => $summary]);
    }

    /**
     * Parse a JD CSV. Header row maps columns; role_title + raw_jd are required
     * per row. Capped to protect the queue from a runaway upload.
     *
     * @return list<array<string, mixed>>
     */
    private function parseCsv(string $path, ?int $courseId): array
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            return [];
        }

        $header = null;
        $rows = [];
        $max = 2000;

        while (($line = fgetcsv($handle)) !== false && count($rows) < $max) {
            if ($header === null) {
                $header = array_map(fn ($h) => mb_strtolower(trim((string) $h)), $line);

                continue;
            }

            $record = [];
            foreach ($header as $i => $key) {
                $record[$key] = isset($line[$i]) ? trim((string) $line[$i]) : null;
            }

            if (($record['role_title'] ?? '') === '' || ($record['raw_jd'] ?? '') === '') {
                continue;
            }

            $rows[] = [
                'role_title' => $record['role_title'],
                'raw_jd' => $record['raw_jd'],
                'company' => $record['company'] ?? null,
                'location' => $record['location'] ?? null,
                'seniority' => $record['seniority'] ?? null,
                'external_ref' => $record['external_ref'] ?? null,
                'source' => 'csv',
                'course_id' => $courseId,
            ];
        }

        fclose($handle);

        return $rows;
    }
}
