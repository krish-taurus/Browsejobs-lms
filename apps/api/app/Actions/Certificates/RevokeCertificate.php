<?php

declare(strict_types=1);

namespace App\Actions\Certificates;

use App\Models\Certificate;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use App\Support\Tenancy\TenantContext;

/**
 * Revokes a certificate (PRD §6.5). Idempotent. The public verify endpoint reads
 * `revoked_at` live, so no re-render is needed — revocation is instant.
 */
final readonly class RevokeCertificate
{
    public function __construct(private AuditLogger $audit) {}

    public function handle(Certificate $certificate, ?User $actor = null): Certificate
    {
        return app(TenantContext::class)->run($certificate->tenant, function () use ($certificate, $actor): Certificate {
            if ($certificate->isRevoked()) {
                return $certificate;
            }

            $certificate->update(['revoked_at' => now()]);

            $this->audit->log(
                action: 'certificate.revoked',
                target: $certificate,
                metadata: ['code' => $certificate->code],
                actor: $actor,
            );

            return $certificate->refresh();
        });
    }
}
