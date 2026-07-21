<?php

declare(strict_types=1);

namespace App\Actions\Projects;

use App\Enums\ArchitectureSource;
use App\Models\ProjectSpec;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

/**
 * Render a project's AI-generated architecture (overview + layers + flows) into
 * a branded, downloadable PDF on the s3 disk, and mark the spec's architecture
 * source as AI. The path is stored on the spec so students can download it.
 * Overwrites any previously generated PDF.
 */
final class RenderProjectPdf
{
    public const DISK = 's3';

    public function handle(ProjectSpec $spec): string
    {
        $spec->loadMissing(['lesson.topic.module.course', 'tenant']);

        $module = $spec->lesson?->topic?->module;

        $pdf = Pdf::loadView('pdf.project', [
            'tenantName' => $spec->tenant?->name ?? 'BrowseJobs',
            'courseName' => $module?->course?->name ?? 'the course',
            'title' => $spec->title,
            'summary' => (string) ($spec->summary ?? ''),
            'techStack' => $spec->tech_stack ?? [],
            'architecture' => is_array($spec->architecture) ? $spec->architecture : ['overview' => '', 'layers' => [], 'flows' => []],
            'generatedOn' => now()->format('j F Y'),
        ])->setPaper('a4');

        $path = "projects/{$spec->tenant_id}/lesson-{$spec->lesson_id}.pdf";
        Storage::disk(self::DISK)->put($path, $pdf->output(), 'private');

        $spec->update([
            'architecture_path' => $path,
            'architecture_source' => ArchitectureSource::Ai,
        ]);

        return $path;
    }
}
