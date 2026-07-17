<?php

declare(strict_types=1);

namespace App\Support\Files;

use ZipArchive;

/**
 * Deterministic text extraction from .docx (a zip whose body text lives in
 * word/document.xml). Shared by the interview-transcript pipeline and CV
 * import — no external dependency.
 */
final class DocxExtractor
{
    public function extract(string $contents): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'bjl-docx');
        file_put_contents($tmp, $contents);

        try {
            $zip = new ZipArchive;
            if ($zip->open($tmp) !== true) {
                return '';
            }

            $xml = (string) $zip->getFromName('word/document.xml');
            $zip->close();

            // Paragraph/break tags become newlines so structure survives.
            $xml = (string) preg_replace('/<w:(p|br)[^>]*>/', "\n", $xml);

            return trim(html_entity_decode(strip_tags($xml)));
        } finally {
            @unlink($tmp);
        }
    }
}
