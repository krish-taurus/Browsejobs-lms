<?php

declare(strict_types=1);

namespace App\Actions\Quizzes;

use App\Models\Quiz;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

/**
 * Render a day's MCQ assignment into a printable A4 paper (ADR 0049): the
 * questions and options, followed by a trainer-only answer key on its own page.
 * Stored private on s3 like lesson-note PDFs; a manually uploaded paper
 * (pdf_uploaded) is guarded by the controller and never overwritten silently.
 */
final class RenderQuizPdf
{
    public const DISK = 's3';

    public function handle(Quiz $quiz): string
    {
        $quiz->loadMissing(['lesson.topic.module.course', 'questions', 'tenant']);

        $topic = $quiz->lesson?->topic;
        $module = $topic?->module;

        $pdf = Pdf::loadView('pdf.assignment', [
            'tenantName' => $quiz->tenant?->name ?? 'BrowseJobs',
            'courseName' => $module?->course?->name ?? 'the course',
            'moduleName' => $module?->name ?? 'Module',
            'dayLabel' => $topic?->day_number ? "Day {$topic->day_number}" : null,
            'topicName' => $topic?->name ?? 'Topic',
            'title' => $quiz->title,
            'instructions' => $quiz->instructions,
            'questions' => $quiz->questions,
            'generatedOn' => now()->format('j F Y'),
        ])->setPaper('a4');

        $path = "assignments/{$quiz->tenant_id}/lesson-{$quiz->lesson_id}.pdf";
        Storage::disk(self::DISK)->put($path, $pdf->output(), 'private');

        $quiz->update(['pdf_path' => $path, 'pdf_uploaded' => false]);

        return $path;
    }
}
