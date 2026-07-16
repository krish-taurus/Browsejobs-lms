<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\BatchMemberStatus;
use App\Enums\BatchType;
use App\Models\Batch;
use App\Models\BatchMember;
use App\Models\Course;
use App\Models\Lead;
use App\Models\LeadStage;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Crm\PhoneNormalizer;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Seeder;

/**
 * Seeds a demo bootcamp linked to the seeded paid batch with an attendee, so the
 * Stage-3 "complete bootcamp → convert" flow and the funnel are demo-able.
 * Idempotent.
 */
class ConversionSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::query()->where('slug', 'browsejobs')->first();
        if ($tenant === null) {
            return;
        }

        app(TenantContext::class)->run($tenant, fn () => $this->seedTenant($tenant));
    }

    private function seedTenant(Tenant $tenant): void
    {
        $paid = Batch::query()->where('tenant_id', $tenant->id)->where('number', 'DE-202607-PAY')->first();
        if ($paid === null) {
            return; // PaymentsSeeder hasn't run
        }

        if (Batch::query()->where('tenant_id', $tenant->id)->where('number', 'DE-202607-BC')->exists()) {
            return; // already seeded
        }

        $course = Course::query()->where('tenant_id', $tenant->id)->first()
            ?? Course::query()->create(['tenant_id' => $tenant->id, 'code' => 'DE', 'name' => 'Data Engineering', 'slug' => 'data-engineering', 'status' => 'live']);

        $bootcamp = Batch::query()->create([
            'tenant_id' => $tenant->id,
            'course_id' => $course->id,
            'number' => 'DE-202607-BC',
            'type' => BatchType::Bootcamp->value,
            'status' => 'active',
        ]);

        // Link the paid batch back to the bootcamp that feeds it (Stage 3).
        $paid->update(['linked_source_batch_id' => $bootcamp->id]);

        $attendee = User::query()->firstOrCreate(
            ['email' => 'arjun.menon@example.com'],
            ['tenant_id' => $tenant->id, 'name' => 'Arjun Menon', 'user_type' => 'student', 'phone' => '+919876530000'],
        );

        BatchMember::query()->firstOrCreate(
            ['batch_id' => $bootcamp->id, 'user_id' => $attendee->id],
            ['tenant_id' => $tenant->id, 'status' => BatchMemberStatus::Enrolled->value, 'enrolled_at' => now()->subDays(2)],
        );

        // A matching CRM lead at the 'bootcamp' stage for the conversion + funnel demo.
        $bootcampStage = LeadStage::query()->where('tenant_id', $tenant->id)->where('slug', 'bootcamp')->value('id');
        Lead::query()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'phone_normalized' => PhoneNormalizer::normalize('+919876530000')],
            [
                'lead_type' => 'bootcamp', 'name' => 'Arjun Menon', 'phone' => '+919876530000',
                'email' => 'arjun.menon@example.com', 'utm_campaign' => 'de-launch-aug',
                'consented_at' => now()->subDays(10), 'consent_version' => 'v1', 'lead_stage_id' => $bootcampStage,
            ],
        );
    }
}
