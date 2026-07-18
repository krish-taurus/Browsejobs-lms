<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Market\GenerateSyllabusRecommendation;
use App\Models\Course;
use App\Models\Syllabus;
use App\Models\SyllabusRecommendation;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Console\Command;

/**
 * Quarterly Syllabus Recommendation Reports (PRD §6.21). One report per course
 * that already has a syllabus, per active tenant, marked as scheduled. Billing
 * attaches to the tenant's first staff user. Reports queue for trainer review —
 * nothing is applied automatically.
 */
final class RunSyllabusRecommendations extends Command
{
    protected $signature = 'curriculum:recommend';

    protected $description = 'Generate quarterly syllabus recommendation reports per course';

    public function handle(GenerateSyllabusRecommendation $generate): int
    {
        $built = 0;

        foreach (Tenant::query()->where('status', 'active')->get() as $tenant) {
            $billTo = User::query()->withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)
                ->where('user_type', 'staff')
                ->orderBy('id')
                ->first();

            if ($billTo === null) {
                continue;
            }

            app(TenantContext::class)->run($tenant, function () use ($generate, $billTo, &$built): void {
                $courseIds = Syllabus::query()->pluck('course_id')->unique();

                foreach (Course::query()->whereIn('id', $courseIds)->get() as $course) {
                    $generate->handle($billTo, $course, SyllabusRecommendation::SOURCE_SCHEDULED);
                    $built++;
                }
            });
        }

        $this->info("Generated {$built} syllabus recommendation report(s).");

        return self::SUCCESS;
    }
}
