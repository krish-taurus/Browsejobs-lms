<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\JobFeed\IngestJobFeed;
use App\Http\Controllers\Controller;
use App\Models\JobFeedItem;
use App\Models\JobFeedSource;
use App\Support\JobFeed\Adapters\ScraperAdapter;
use App\Support\JobFeed\NormalizedJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Job-feed source management + ingestion (PRD §6.22). Placement team configures
 * sources, triggers a sync, and bulk-imports postings via CSV. Scraper sources
 * (Apify actors — ADR 0048 founder override) are configured here like any other.
 */
final class JobFeedController extends Controller
{
    public function index(): JsonResponse
    {
        $sources = JobFeedSource::query()
            ->withCount(['items as active_items' => fn ($q) => $q->where('status', JobFeedItem::STATUS_ACTIVE)])
            ->orderByDesc('priority')
            ->get()
            ->map(fn (JobFeedSource $s) => [
                'id' => $s->id,
                'name' => $s->name,
                'kind' => $s->kind,
                'priority' => $s->priority,
                'is_active' => $s->is_active,
                'active_items' => $s->active_items,
                'last_synced_at' => $s->last_synced_at?->toIso8601String(),
            ]);

        $recent = JobFeedItem::query()
            ->where('status', JobFeedItem::STATUS_ACTIVE)
            ->orderByDesc('ingested_at')
            ->limit(30)
            ->get()
            ->map(fn (JobFeedItem $i) => [
                'id' => $i->id,
                'title' => $i->title,
                'company' => $i->company,
                'location' => $i->location,
                'source_kind' => $i->source_kind,
                'skills' => $i->extracted_skills ?? [],
                'posted_at' => $i->posted_at?->toDateString(),
            ]);

        return response()->json(['data' => ['sources' => $sources, 'recent' => $recent]]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:190'],
            'kind' => ['required', Rule::in(JobFeedSource::KINDS)],
            'priority' => ['nullable', 'integer', 'between:0,100'],
            // Adapter configuration: the API kind takes `query`; the scraper kind
            // (ADR 0048) takes `actor_id` + `input`; any kind may set freshness_days.
            'config' => ['nullable', 'array'],
            'config.actor_id' => ['required_if:kind,scraper', 'string', 'max:190'],
            'config.input' => ['nullable', 'array'],
            'config.query' => ['nullable', 'array'],
            'config.freshness_days' => ['nullable', 'integer', 'between:1,90'],
        ]);

        $config = (array) ($data['config'] ?? []);
        if ($data['kind'] === JobFeedSource::KIND_SCRAPER) {
            // Scraped boards age fast — the founder wants a rolling 7-day window.
            $config['freshness_days'] ??= ScraperAdapter::DEFAULT_FRESHNESS_DAYS;
        }

        $source = JobFeedSource::query()->create([
            'tenant_id' => $request->user()->tenant_id,
            'name' => $data['name'],
            'kind' => $data['kind'],
            'config' => $config === [] ? null : $config,
            'priority' => $data['priority'] ?? 0,
            'is_active' => true,
        ]);

        return response()->json(['data' => ['id' => $source->id]], 201);
    }

    public function sync(JobFeedSource $source, IngestJobFeed $ingest): JsonResponse
    {
        $summary = $ingest->fromSource($source);

        return response()->json(['data' => $summary]);
    }

    public function import(Request $request, JobFeedSource $source, IngestJobFeed $ingest): JsonResponse
    {
        $request->validate(['file' => ['required', 'file', 'mimes:csv,txt', 'max:5120']]);

        $jobs = $this->parseCsv((string) $request->file('file')->getRealPath());
        if ($jobs === []) {
            return response()->json([
                'error' => ['code' => 'empty_import', 'message' => 'No usable rows. Expected columns: title, company, description (+ optional location, apply_url).'],
            ], 422);
        }

        return response()->json(['data' => $ingest->push($source, $jobs)]);
    }

    /**
     * @return list<NormalizedJob>
     */
    private function parseCsv(string $path): array
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            return [];
        }

        $header = null;
        $jobs = [];
        while (($line = fgetcsv($handle)) !== false && count($jobs) < 2000) {
            if ($header === null) {
                $header = array_map(fn ($h) => mb_strtolower(trim((string) $h)), $line);

                continue;
            }

            $row = [];
            foreach ($header as $i => $key) {
                $row[$key] = isset($line[$i]) ? trim((string) $line[$i]) : null;
            }

            if (($row['title'] ?? '') === '' || ($row['company'] ?? '') === '' || ($row['description'] ?? '') === '') {
                continue;
            }

            $jobs[] = new NormalizedJob(
                title: (string) $row['title'],
                company: (string) $row['company'],
                description: (string) $row['description'],
                externalId: $row['external_id'] ?? null,
                location: $row['location'] ?? null,
                workMode: $row['work_mode'] ?? null,
                applyUrl: $row['apply_url'] ?? null,
                roleTitle: $row['role_title'] ?? (string) $row['title'],
            );
        }

        fclose($handle);

        return $jobs;
    }
}
