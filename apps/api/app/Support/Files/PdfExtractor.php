<?php

declare(strict_types=1);

namespace App\Support\Files;

use Smalot\PdfParser\Parser;
use Throwable;

/**
 * Text extraction from .pdf, alongside DocxExtractor — CV upload (spec
 * item 1) named PDF explicitly, and most exported resumes are PDFs, not
 * .docx. A scanned/image-only PDF has no embedded text layer and comes
 * back empty; that is reported to the candidate as a read failure rather
 * than silently importing nothing, same as a corrupt .docx does today.
 */
final class PdfExtractor
{
    public function extract(string $contents): string
    {
        try {
            $document = (new Parser)->parseContent($contents);

            return trim($document->getText());
        } catch (Throwable) {
            // Encrypted, corrupt, or a format variant the parser cannot
            // read — the caller treats an empty string as "could not read".
            return '';
        }
    }
}
