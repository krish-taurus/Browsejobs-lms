<?php

declare(strict_types=1);

namespace App\Support\Syllabus;

use App\Models\Syllabus;

/**
 * Renders an approved course syllabus to a branded document and returns the storage
 * path (PRD §6.2). Branded HTML now (HtmlSyllabusRenderer); a WeasyPrint/PDF binarizer
 * can swap in behind this same interface later, exactly like CertificateRenderer.
 * Called only from the queued RenderSyllabus job.
 */
interface SyllabusRenderer
{
    public function render(Syllabus $syllabus): string;
}
