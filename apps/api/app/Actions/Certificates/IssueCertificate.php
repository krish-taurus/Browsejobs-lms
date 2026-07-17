<?php

declare(strict_types=1);

namespace App\Actions\Certificates;

use App\Jobs\RenderCertificate;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use App\Support\Messaging\Messenger;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Issues a course-completion certificate (PRD §6.5). Idempotent — one certificate per
 * (student, course), so the auto-on-completion path and a manual admin issue converge
 * on the same record. Generates an unguessable global code + per-tenant serial, queues
 * the render, audits, and notifies the student.
 */
final readonly class IssueCertificate
{
    public function __construct(
        private AuditLogger $audit,
        private Messenger $messenger,
    ) {}

    public function handle(User $student, Course $course, ?User $actor = null): Certificate
    {
        return app(TenantContext::class)->run($student->tenant, function () use ($student, $course, $actor): Certificate {
            $existing = Certificate::query()
                ->where('user_id', $student->id)
                ->where('course_id', $course->id)
                ->first();

            if ($existing !== null) {
                return $existing; // auto + manual converge; never a duplicate
            }

            $certificate = DB::transaction(fn (): Certificate => Certificate::query()->create([
                'tenant_id' => $student->tenant_id,
                'user_id' => $student->id,
                'course_id' => $course->id,
                'code' => $this->uniqueCode(),
                'number' => $this->nextNumber($student->tenant_id),
                'title' => 'Certificate of Completion — '.$course->name,
                'issued_at' => now(),
                'status' => Certificate::STATUS_PENDING,
                'issued_by' => $actor?->id,
            ]));

            RenderCertificate::dispatch($certificate->id);

            $this->audit->log(
                action: 'certificate.issued',
                target: $certificate,
                metadata: ['course_id' => $course->id, 'code' => $certificate->code],
                actor: $actor,
            );

            $verifyUrl = rtrim((string) config('app.frontend_url'), '/').'/verify/'.$certificate->code;
            $this->messenger->send($student, 'certificate_ready', [
                'name' => $student->name,
                'course' => $course->name,
                'link' => $verifyUrl,
            ]);

            return $certificate;
        });
    }

    /** An unguessable, globally-unique code (the global unique index is the backstop). */
    private function uniqueCode(): string
    {
        do {
            $code = Str::lower(Str::random(40));
        } while (Certificate::query()->withoutGlobalScopes()->where('code', $code)->exists());

        return $code;
    }

    /** Per-tenant sequential certificate number, zero-padded. */
    private function nextNumber(?int $tenantId): string
    {
        $count = Certificate::query()->where('tenant_id', $tenantId)->count();

        return 'CERT-'.str_pad((string) ($count + 1), 6, '0', STR_PAD_LEFT);
    }
}
