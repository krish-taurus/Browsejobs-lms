<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\SyllabusStatus;
use App\Models\Syllabus;
use App\Models\Tenant;
use App\Support\Syllabus\SyllabusRenderer;
use App\Support\Tenancy\TenantContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Renders + stores an approved syllabus's branded document (PRD §6.2; renders are
 * queued jobs per CLAUDE.md). Re-renders the current approved content and swaps the
 * served artifact only on success — so the previously rendered (approved) document
 * keeps serving until this completes (PRD §6.21). Never renders a draft. Mirrors
 * RenderCertificate.
 */
final class RenderSyllabus implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public readonly int $syllabusId) {}

    public function handle(SyllabusRenderer $renderer): void
    {
        $syllabus = Syllabus::query()->withoutGlobalScopes()->find($this->syllabusId);
        if ($syllabus === null) {
            return;
        }

        // Never render an unapproved draft into a serveable artifact. (No render_status
        // skip: a re-approval leaves the row 'rendered' so the old doc keeps serving,
        // and this job must still re-render the newly approved content.)
        if ($syllabus->status !== SyllabusStatus::Approved) {
            return;
        }

        $tenant = Tenant::query()->find($syllabus->tenant_id);
        if ($tenant === null) {
            return;
        }

        app(TenantContext::class)->run($tenant, function () use ($syllabus, $renderer): void {
            $path = $renderer->render($syllabus);

            $syllabus->storage_path = $path;
            $syllabus->render_status = Syllabus::RENDER_RENDERED;
            $syllabus->save();
        });
    }
}
