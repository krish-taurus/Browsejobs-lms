<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\Certificates\IssueCertificate;
use App\Models\BatchMember;
use App\Models\Certificate;
use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Seeder;

/**
 * Makes certificates (P3.4c) demo-able: issues + renders a certificate for a seeded
 * student's course so /verify/{code}, the portal, and the admin list all have data.
 */
class CertificateSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::query()->where('slug', 'browsejobs')->first();
        if ($tenant === null) {
            return;
        }

        app(TenantContext::class)->run($tenant, function (): void {
            $member = BatchMember::query()
                ->whereIn('status', ['enrolled', 'reserved', 'payment_pending', 'completed'])
                ->with('batch.course')
                ->first();

            $course = $member?->batch?->course;
            if ($member === null || $course === null) {
                return;
            }

            if (Certificate::query()->where('user_id', $member->user_id)->where('course_id', $course->id)->exists()) {
                return;
            }

            // The cert row is what the verify/portal/admin surfaces need; a storage
            // hiccup while rendering (e.g. no MinIO in CI) must not break seeding.
            try {
                app(IssueCertificate::class)->handle($member->student, $course);
            } catch (\Throwable $e) {
                // Left pending; the render retries in an environment with storage.
            }
        });
    }
}
