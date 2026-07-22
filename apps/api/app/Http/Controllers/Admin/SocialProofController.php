<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\SocialProof\ImportPlacementStories;
use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseInterviewQuestion;
use App\Models\PlacementStory;
use App\Models\Review;
use App\Support\Import\XlsxReader;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Admin authoring for course-page social proof (Platform Spec §3), gated by
 * `can:manage-curriculum`: placement stories (+ screenshot upload), the per-course
 * interview-question bank (bulk replace), and course-tagged reviews. The public
 * {@see \App\Http\Controllers\Courses\SocialProofController} reads what's authored
 * here.
 */
final class SocialProofController extends Controller
{
    /* ---------- Placement stories ---------- */

    public function stories(Course $course): JsonResponse
    {
        $stories = PlacementStory::query()->where('course_id', $course->id)
            ->orderBy('position')->orderBy('id')->get()
            ->map(fn (PlacementStory $s) => $this->storyPayload($s));

        return response()->json(['data' => $stories]);
    }

    public function storeStory(Request $request, Course $course): JsonResponse
    {
        $data = $this->validateStory($request);
        // A human-curated story is real, never a seeded sample.
        $story = PlacementStory::query()->create([...$data, 'course_id' => $course->id, 'is_sample' => false]);

        return response()->json(['data' => $this->storyPayload($story)], 201);
    }

    /** Header columns for the bulk placement-story spreadsheet. */
    private const STORY_HEADERS = [
        'course_slug', 'student_name', 'before_label', 'after_role',
        'package_label', 'company_name', 'company_color', 'rounds', 'quote', 'consent',
    ];

    /** A ready-to-fill CSV (opens in Excel) with example rows. */
    public function storiesTemplate(): Response
    {
        $rows = [
            self::STORY_HEADERS,
            ['data-engineering', 'Aarti Sharma', 'Support engineer, 3 yrs', 'Data Engineer', '₹12 LPA', 'Acme Data', '#12408f', '4', 'Cleared it — the SQL round was exactly what we practised!', 'yes'],
            ['devops-cloud', 'Rahul Verma', 'Fresher, non-CS', 'DevOps Engineer', '₹9 LPA', 'Cloudspire', '', '3', 'Got the offer today 🎉', 'no'],
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
            'Content-Disposition' => 'attachment; filename="browsejobs-placement-stories-template.csv"',
        ]);
    }

    /** Bulk-import placement stories from a CSV or .xlsx upload. */
    public function importStories(Request $request, ImportPlacementStories $import): JsonResponse
    {
        $request->validate(['file' => ['required', 'file', 'max:4096']]);

        $file = $request->file('file');
        $isXlsx = strtolower((string) $file->getClientOriginalExtension()) === 'xlsx';

        try {
            $grid = $isXlsx ? XlsxReader::rows($file->getRealPath()) : $this->readCsv($file->getRealPath());
        } catch (Throwable $e) {
            throw ValidationException::withMessages(['file' => $e->getMessage()]);
        }

        $summary = $import->handle(app(TenantContext::class)->get(), $this->gridToRows($grid));

        return response()->json(['data' => $summary], 201);
    }

    /** @return list<list<string>> */
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
     * Header-keyed rows from a raw grid; column order/casing don't matter.
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

        $missing = array_diff(['course_slug', 'student_name', 'before_label', 'after_role'], $header);
        if ($missing !== []) {
            throw ValidationException::withMessages([
                'file' => 'Missing required columns: '.implode(', ', $missing).'. Download the template.',
            ]);
        }

        $rows = [];
        foreach ($grid as $data) {
            if (implode('', array_map('strval', $data)) === '') {
                continue;
            }
            $row = [];
            foreach ($header as $i => $col) {
                $row[$col] = isset($data[$i]) ? (string) $data[$i] : '';
            }
            $rows[] = $row;
        }

        return $rows;
    }

    public function updateStory(Request $request, PlacementStory $story): JsonResponse
    {
        // Editing a story promotes it from a seeded sample to a real story.
        $story->update([...$this->validateStory($request), 'is_sample' => false]);

        return response()->json(['data' => $this->storyPayload($story->refresh())]);
    }

    public function destroyStory(PlacementStory $story): JsonResponse
    {
        $story->delete();

        return response()->json(['data' => ['deleted' => true]]);
    }

    /** Attach (or replace) the WhatsApp offer screenshot. */
    public function uploadScreenshot(Request $request, PlacementStory $story): JsonResponse
    {
        $request->validate([
            'image' => ['required', 'file', 'image', 'max:8192'],
        ]);

        $path = $request->file('image')->store("placement-stories/{$story->tenant_id}", 's3');
        $story->update(['screenshot_path' => $path]);

        return response()->json(['data' => $this->storyPayload($story->refresh())]);
    }

    /* ---------- Interview-question bank ---------- */

    public function questions(Course $course): JsonResponse
    {
        $rounds = CourseInterviewQuestion::query()->where('course_id', $course->id)
            ->orderBy('round_no')->orderBy('position')->orderBy('id')->get()
            ->groupBy('round_no')
            ->map(fn ($rows) => [
                'round_no' => (int) $rows->first()->round_no,
                'round_name' => (string) $rows->first()->round_name,
                'company' => $rows->first()->company,
                'questions' => $rows->pluck('question')->all(),
            ])->values();

        return response()->json(['data' => $rounds]);
    }

    /** Replace the whole question bank for a course (paste-and-save). */
    public function upsertQuestions(Request $request, Course $course): JsonResponse
    {
        $validated = $request->validate([
            'rounds' => ['array', 'max:20'],
            'rounds.*.round_no' => ['required', 'integer', 'min:1', 'max:20'],
            'rounds.*.round_name' => ['required', 'string', 'max:200'],
            'rounds.*.company' => ['nullable', 'string', 'max:120'],
            'rounds.*.questions' => ['array', 'max:200'],
            'rounds.*.questions.*' => ['required', 'string', 'max:1000'],
        ]);

        DB::transaction(function () use ($course, $validated): void {
            CourseInterviewQuestion::query()->where('course_id', $course->id)->delete();

            foreach (array_values($validated['rounds'] ?? []) as $round) {
                foreach (array_values($round['questions'] ?? []) as $i => $question) {
                    CourseInterviewQuestion::query()->create([
                        'tenant_id' => $course->tenant_id,
                        'course_id' => $course->id,
                        'company' => $round['company'] ?? null,
                        'round_no' => $round['round_no'],
                        'round_name' => $round['round_name'],
                        'question' => trim($question),
                        'is_published' => true,
                        'position' => $i,
                    ]);
                }
            }
        });

        return $this->questions($course);
    }

    /* ---------- Course-tagged reviews ---------- */

    public function reviews(Course $course): JsonResponse
    {
        $reviews = Review::query()->where('course_slug', $course->slug)
            ->orderBy('sort_order')->orderByDesc('id')->get(['id', 'author_name', 'author_meta', 'rating', 'body', 'source', 'is_active']);

        return response()->json(['data' => $reviews]);
    }

    public function storeReview(Request $request, Course $course): JsonResponse
    {
        $data = $request->validate([
            'author_name' => ['required', 'string', 'max:190'],
            'author_meta' => ['nullable', 'string', 'max:190'],
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'source' => ['required', Rule::in(Review::SOURCES)],
            'body' => ['required', 'string', 'max:4000'],
        ]);

        $review = Review::query()->create([
            ...$data,
            'course_slug' => $course->slug,
            'is_active' => true,
            'sort_order' => (int) Review::query()->where('course_slug', $course->slug)->max('sort_order') + 1,
        ]);

        return response()->json(['data' => $review->only(['id', 'author_name', 'author_meta', 'rating', 'body', 'source', 'is_active'])], 201);
    }

    public function destroyReview(Review $review): JsonResponse
    {
        $review->delete();

        return response()->json(['data' => ['deleted' => true]]);
    }

    /* ---------- helpers ---------- */

    /** @return array<string, mixed> */
    private function validateStory(Request $request): array
    {
        return $request->validate([
            'student_name' => ['required', 'string', 'max:190'],
            'before_label' => ['required', 'string', 'max:200'],
            'after_role' => ['required', 'string', 'max:120'],
            'package_label' => ['nullable', 'string', 'max:40'],
            'company_name' => ['nullable', 'string', 'max:120'],
            'company_color' => ['nullable', 'string', 'max:9'],
            'rounds' => ['nullable', 'integer', 'min:1', 'max:20'],
            'quote' => ['nullable', 'string', 'max:2000'],
            'consent' => ['nullable', 'boolean'],
            'is_published' => ['nullable', 'boolean'],
            'position' => ['nullable', 'integer', 'min:0'],
        ]);
    }

    /** @return array<string, mixed> */
    private function storyPayload(PlacementStory $s): array
    {
        return [
            'id' => $s->id,
            'student_name' => $s->student_name,
            'before_label' => $s->before_label,
            'after_role' => $s->after_role,
            'package_label' => $s->package_label,
            'company_name' => $s->company_name,
            'company_color' => $s->company_color,
            'rounds' => $s->rounds,
            'quote' => $s->quote,
            'screenshot_url' => $s->screenshotUrl(),
            'has_screenshot' => $s->screenshot_path !== null,
            'consent' => $s->consent,
            'is_published' => $s->is_published,
            'is_sample' => $s->is_sample,
            'position' => $s->position,
        ];
    }
}
