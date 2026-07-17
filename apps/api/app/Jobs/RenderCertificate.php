<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Certificate;
use App\Models\Tenant;
use App\Support\Certificates\CertificateRenderer;
use App\Support\Tenancy\TenantContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Renders + stores a certificate's branded HTML document (PRD §6.5; renders are queued
 * jobs per CLAUDE.md). Idempotent: skips an already-rendered certificate. Mirrors
 * RenderReceipt.
 */
final class RenderCertificate implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public readonly int $certificateId) {}

    public function handle(CertificateRenderer $renderer): void
    {
        $certificate = Certificate::query()->withoutGlobalScopes()->find($this->certificateId);
        if ($certificate === null || $certificate->status === Certificate::STATUS_RENDERED) {
            return;
        }

        $tenant = Tenant::query()->find($certificate->tenant_id);
        if ($tenant === null) {
            return;
        }

        app(TenantContext::class)->run($tenant, function () use ($certificate, $renderer): void {
            $path = $renderer->render($certificate);

            $certificate->storage_path = $path;
            $certificate->status = Certificate::STATUS_RENDERED;
            $certificate->save();
        });
    }
}
