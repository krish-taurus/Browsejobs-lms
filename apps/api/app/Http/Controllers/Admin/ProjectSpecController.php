<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Projects\GenerateProjectArchitecture;
use App\Actions\Projects\RenderProjectPdf;
use App\Enums\ArchitectureSource;
use App\Enums\LessonType;
use App\Http\Controllers\Controller;
use App\Models\Lesson;
use App\Models\ProjectSpec;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Admin/trainer builder for a `project` lesson's capstone spec (tech stack +
 * details + architecture). Gated by `can:manage-curriculum`. The architecture is
 * either AI-generated (rendered to a branded PDF) or an uploaded file — both are
 * stored on the Space and offered to students as a signed download.
 */
final class ProjectSpecController extends Controller
{
    public function show(Lesson $lesson): JsonResponse
    {
        $spec = ProjectSpec::query()->where('lesson_id', $lesson->id)->first();

        return response()->json(['data' => $spec === null ? null : $this->payload($spec)]);
    }

    public function upsert(Request $request, Lesson $lesson): JsonResponse
    {
        $this->assertProject($lesson);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:190'],
            'summary' => ['nullable', 'string', 'max:5000'],
            'tech_stack' => ['array', 'max:40'],
            'tech_stack.*' => ['string', 'max:60'],
            'architecture' => ['nullable', 'array'],
        ]);

        $values = [
            'tenant_id' => $lesson->tenant_id,
            'title' => $validated['title'],
            'summary' => $validated['summary'] ?? null,
            'tech_stack' => array_values(array_filter(array_map('trim', $validated['tech_stack'] ?? []))),
        ];

        // Only touch the architecture when the key is explicitly sent, so a
        // details-only save never wipes a previously saved architecture.
        if ($request->has('architecture')) {
            $values['architecture'] = is_array($validated['architecture'] ?? null)
                ? $this->sanitizeArchitecture($validated['architecture'])
                : null;
        }

        $spec = ProjectSpec::query()->updateOrCreate(['lesson_id' => $lesson->id], $values);

        return response()->json(['data' => $this->payload($spec->fresh())]);
    }

    /** AI-draft an architecture from the saved title/details/tech stack. Draft only. */
    public function generate(Request $request, Lesson $lesson, GenerateProjectArchitecture $generate): JsonResponse
    {
        $this->assertProject($lesson);

        $spec = ProjectSpec::query()->where('lesson_id', $lesson->id)->first();
        abort_if($spec === null, 422, 'Save the project details first.');

        $draft = $generate->handle($lesson, $spec, $request->user());

        if ($draft === null) {
            return response()->json(['error' => [
                'code' => 'generation_failed',
                'message' => 'The architecture could not be generated. Try again.',
                'details' => null,
            ]], 422);
        }

        return response()->json(['data' => $draft]);
    }

    /** Render the saved architecture into a downloadable branded PDF. */
    public function pdf(Lesson $lesson, RenderProjectPdf $render): JsonResponse
    {
        $this->assertProject($lesson);

        $spec = ProjectSpec::query()->where('lesson_id', $lesson->id)->first();
        abort_if($spec === null || ! $spec->hasStructure(), 422, 'Generate and save an architecture first.');

        $render->handle($spec);

        return response()->json(['data' => ['url' => $this->downloadUrl($spec->fresh())]]);
    }

    /** Attach an uploaded architecture file (diagram image or PDF) as the download. */
    public function upload(Request $request, Lesson $lesson): JsonResponse
    {
        $this->assertProject($lesson);

        $request->validate([
            'file' => ['required', 'file', 'mimes:pdf,png,jpg,jpeg', 'max:10240'],
        ]);

        $spec = ProjectSpec::query()->where('lesson_id', $lesson->id)->first();
        abort_if($spec === null, 422, 'Save the project details first.');

        $ext = $request->file('file')->getClientOriginalExtension() ?: 'pdf';
        $path = "projects/{$spec->tenant_id}/lesson-{$spec->lesson_id}-upload.{$ext}";
        Storage::disk(RenderProjectPdf::DISK)->put($path, $request->file('file')->getContent(), 'private');

        $spec->update(['architecture_path' => $path, 'architecture_source' => ArchitectureSource::Upload]);

        return response()->json(['data' => ['url' => $this->downloadUrl($spec->fresh())]]);
    }

    private function assertProject(Lesson $lesson): void
    {
        abort_unless($lesson->type === LessonType::Project, 422, 'Lesson is not a project.');
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return array{overview: string, layers: list<array{name: string, components: list<array{name: string, role: string}>}>, flows: list<array{from: string, to: string, label: string}>}
     */
    private function sanitizeArchitecture(array $raw): array
    {
        $layers = [];
        foreach ((array) ($raw['layers'] ?? []) as $layer) {
            if (! is_array($layer) || ! isset($layer['name'])) {
                continue;
            }
            $components = [];
            foreach ((array) ($layer['components'] ?? []) as $c) {
                if (! is_array($c) || ! isset($c['name'])) {
                    continue;
                }
                $components[] = ['name' => trim((string) $c['name']), 'role' => trim((string) ($c['role'] ?? ''))];
            }
            if ($components !== []) {
                $layers[] = ['name' => trim((string) $layer['name']), 'components' => $components];
            }
        }

        $flows = [];
        foreach ((array) ($raw['flows'] ?? []) as $f) {
            if (! is_array($f) || ! isset($f['from'], $f['to'])) {
                continue;
            }
            $flows[] = ['from' => trim((string) $f['from']), 'to' => trim((string) $f['to']), 'label' => trim((string) ($f['label'] ?? ''))];
        }

        return ['overview' => trim((string) ($raw['overview'] ?? '')), 'layers' => $layers, 'flows' => $flows];
    }

    private function downloadUrl(ProjectSpec $spec): ?string
    {
        if ($spec->architecture_path === null) {
            return null;
        }

        return Storage::disk(RenderProjectPdf::DISK)->temporaryUrl($spec->architecture_path, now()->addMinutes(15));
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(ProjectSpec $spec): array
    {
        return [
            'lesson_id' => $spec->lesson_id,
            'title' => $spec->title,
            'summary' => $spec->summary,
            'tech_stack' => $spec->tech_stack ?? [],
            'architecture' => $spec->architecture,
            'architecture_source' => $spec->architecture_source->value,
            'has_download' => $spec->hasDownload(),
            'download_url' => $this->downloadUrl($spec),
        ];
    }
}
