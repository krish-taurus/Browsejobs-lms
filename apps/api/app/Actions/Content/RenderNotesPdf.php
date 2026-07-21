<?php

declare(strict_types=1);

namespace App\Actions\Content;

use App\Models\LessonNote;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use League\CommonMark\GithubFlavoredMarkdownConverter;

/**
 * Render a lesson's approved study notes (markdown) into a branded, downloadable
 * PDF on the s3 disk. The path is stored on the note so students can download
 * it. Overwrites any previously generated PDF. Uploaded PDFs are left alone
 * (pdf_uploaded guards them elsewhere).
 */
final class RenderNotesPdf
{
    public const DISK = 's3';

    public function handle(LessonNote $note): string
    {
        $note->loadMissing(['lesson.topic.module.course', 'tenant']);

        $converter = new GithubFlavoredMarkdownConverter(['html_input' => 'strip', 'allow_unsafe_links' => false]);
        $html = (string) $converter->convert((string) $note->notes);

        $lesson = $note->lesson;
        $module = $lesson?->topic?->module;

        $pdf = Pdf::loadView('pdf.notes', [
            'tenantName' => $note->tenant?->name ?? 'BrowseJobs',
            'courseName' => $module?->course?->name ?? 'the course',
            'moduleName' => $module?->name ?? 'Module',
            'lessonTitle' => $lesson?->title ?? 'Class notes',
            'generatedOn' => now()->format('j F Y'),
            'body' => $html,
        ])->setPaper('a4');

        $path = "notes/{$note->tenant_id}/lesson-{$note->lesson_id}.pdf";
        Storage::disk(self::DISK)->put($path, $pdf->output(), 'private');

        $note->update(['pdf_path' => $path, 'pdf_uploaded' => false]);

        return $path;
    }
}
