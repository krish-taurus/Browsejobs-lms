<?php

declare(strict_types=1);

namespace App\Support\Certificates;

use App\Models\Certificate;

/**
 * Renders a branded completion certificate and returns the storage path (PRD §6.5).
 * P3.4c renders branded HTML with an inline QR SVG; a PDF binarizer can swap in behind
 * this same interface later. Called only from the queued RenderCertificate job.
 */
interface CertificateRenderer
{
    public function render(Certificate $certificate): string;
}
