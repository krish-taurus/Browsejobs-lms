<?php

declare(strict_types=1);

namespace App\Support\Certificates;

use App\Models\Certificate;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\SvgWriter;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Support\Facades\Storage;

/**
 * Renders a completion certificate as branded HTML with an inline QR SVG (pure-PHP,
 * no GD) and stores it on the documents disk. The QR encodes the host-independent
 * public verification URL. A PDF step can swap in behind {@see CertificateRenderer}.
 */
final class HtmlCertificateRenderer implements CertificateRenderer
{
    public const DISK = 's3';

    public function __construct(private readonly ViewFactory $view) {}

    public function render(Certificate $certificate): string
    {
        $certificate->loadMissing(['student', 'course', 'tenant']);

        $verifyUrl = rtrim((string) config('app.frontend_url'), '/').'/verify/'.$certificate->code;
        $qrSvg = (new SvgWriter)->write(new QrCode($verifyUrl))->getString();

        /** @var array<string, mixed> $branding */
        $branding = $certificate->tenant?->branding ?? [];

        $html = $this->view->make('certificates.default', [
            'tenantName' => $certificate->tenant?->name ?? 'BrowseJobs',
            'studentName' => $certificate->student?->name ?? 'Student',
            'courseName' => $certificate->course?->name ?? 'the course',
            'issuedOn' => $certificate->issued_at?->format('j F Y') ?? now()->format('j F Y'),
            'number' => $certificate->number,
            'code' => $certificate->code,
            'verifyUrl' => $verifyUrl,
            'qrSvg' => $qrSvg,
            'colors' => (array) ($branding['colors'] ?? []),
            'fonts' => (array) ($branding['fonts'] ?? []),
        ])->render();

        $path = "certificates/{$certificate->tenant_id}/{$certificate->code}.html";
        Storage::disk(self::DISK)->put($path, $html);

        return $path;
    }
}
