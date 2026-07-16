<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\ContactTimelineEvent;
use App\Models\CrmAssignmentRule;
use App\Models\CrmTask;
use App\Models\Lead;
use App\Models\LeadStage;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Crm\PhoneNormalizer;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

/**
 * Seeds the CRM pipeline (PRD §6.12): the funnel stages, a default assignment
 * rule, two demo counselors, and a spread of demo leads (incl. a phone-duplicate
 * pair for the merge tool) so /admin/leads is demo-able immediately. Idempotent.
 */
class CrmSeeder extends Seeder
{
    /** @var list<array{name: string, slug: string, is_won?: bool, is_lost?: bool}> */
    private const STAGES = [
        ['name' => 'Lead', 'slug' => 'lead'],
        ['name' => 'Masterclass Registered', 'slug' => 'masterclass-registered'],
        ['name' => 'Attended', 'slug' => 'attended'],
        ['name' => 'Bootcamp', 'slug' => 'bootcamp'],
        ['name' => 'Reserved / Payment Pending', 'slug' => 'reserved'],
        ['name' => 'Enrolled', 'slug' => 'enrolled'],
        ['name' => 'Active', 'slug' => 'active'],
        ['name' => 'Placement-Ready', 'slug' => 'placement-ready'],
        ['name' => 'Placed', 'slug' => 'placed', 'is_won' => true],
    ];

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
        foreach (self::STAGES as $i => $stage) {
            LeadStage::query()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'slug' => $stage['slug']],
                [
                    'name' => $stage['name'],
                    'position' => $i + 1,
                    'is_won' => $stage['is_won'] ?? false,
                    'is_lost' => $stage['is_lost'] ?? false,
                ],
            );
        }

        CrmAssignmentRule::query()->updateOrCreate(
            ['tenant_id' => $tenant->id],
            ['mode' => CrmAssignmentRule::MODE_ROUND_ROBIN, 'sla_minutes' => 15],
        );

        $counselors = $this->seedCounselors($tenant);

        if (Lead::query()->where('tenant_id', $tenant->id)->exists()) {
            return; // Demo leads already seeded — keep idempotent.
        }

        $stages = LeadStage::query()->where('tenant_id', $tenant->id)->orderBy('position')->get();
        $this->seedDemoLeads($tenant, $stages, $counselors);
    }

    /**
     * @return list<User>
     */
    private function seedCounselors(Tenant $tenant): array
    {
        $seed = [
            ['name' => 'Aisha Rahman', 'email' => 'aisha.counselor@browsejobs.test'],
            ['name' => 'Vikram Nair', 'email' => 'vikram.counselor@browsejobs.test'],
        ];

        $counselors = [];
        foreach ($seed as $c) {
            $user = User::query()->firstOrCreate(
                ['email' => $c['email']],
                ['tenant_id' => $tenant->id, 'name' => $c['name'], 'user_type' => 'staff'],
            );
            $user->assignRole('counselor');
            $counselors[] = $user;
        }

        return $counselors;
    }

    /**
     * @param  Collection<int, LeadStage>  $stages
     * @param  list<User>  $counselors
     */
    private function seedDemoLeads(Tenant $tenant, $stages, array $counselors): void
    {
        $names = [
            'Priya Sharma', 'Rahul Verma', 'Sneha Iyer', 'Arjun Menon', 'Divya Reddy',
            'Karthik Rao', 'Meera Nair', 'Sanjay Gupta', 'Ananya Bose', 'Rohit Kulkarni',
        ];

        foreach ($names as $i => $name) {
            $stage = $stages[$i % $stages->count()];
            $counselor = $counselors[$i % count($counselors)] ?? null;
            $phone = '+9198765'.str_pad((string) (10000 + $i), 5, '0', STR_PAD_LEFT);

            $lead = Lead::query()->create([
                'tenant_id' => $tenant->id,
                'lead_type' => ['masterclass', 'counselling', 'bootcamp'][$i % 3],
                'name' => $name,
                'phone' => $phone,
                'phone_normalized' => PhoneNormalizer::normalize($phone),
                'email' => strtolower(str_replace(' ', '.', $name)).'@example.com',
                'course_slug' => ['data-engineering', 'devops-cloud', 'data-analytics'][$i % 3],
                'utm_source' => ['meta', 'google', 'organic'][$i % 3],
                'utm_medium' => 'cpc',
                'page' => '/courses',
                'consented_at' => now()->subDays($i),
                'consent_version' => 'v1',
                'lead_stage_id' => $stage->id,
                'assigned_to' => $counselor?->id,
                'score' => 40 + ($i * 5) % 60,
                'first_touch_at' => $i % 3 === 0 ? null : now()->subDays($i)->addMinutes(8),
            ]);

            ContactTimelineEvent::query()->create([
                'tenant_id' => $tenant->id,
                'lead_id' => $lead->id,
                'type' => ContactTimelineEvent::TYPE_CREATED,
                'body' => 'Lead captured from '.$lead->utm_source,
                'occurred_at' => $lead->consented_at,
            ]);

            if ($counselor !== null && $i % 4 === 0) {
                CrmTask::query()->create([
                    'tenant_id' => $tenant->id,
                    'lead_id' => $lead->id,
                    'assigned_to' => $counselor->id,
                    'title' => "Follow up with {$name}",
                    'due_at' => now()->addDay(),
                    'source' => CrmTask::SOURCE_MANUAL,
                ]);
            }
        }

        // A phone-duplicate pair for the merge tool demo (same normalized phone).
        $dupPhone = '+91 98765 10000';
        Lead::query()->create([
            'tenant_id' => $tenant->id,
            'lead_type' => 'counselling',
            'name' => 'Priya S. (duplicate)',
            'phone' => $dupPhone,
            'phone_normalized' => PhoneNormalizer::normalize($dupPhone),
            'email' => null,
            'course_slug' => 'data-engineering',
            'utm_source' => 'organic',
            'page' => '/contact',
            'consented_at' => now(),
            'consent_version' => 'v1',
            'lead_stage_id' => $stages->first()->id,
            'score' => 20,
        ]);
    }
}
