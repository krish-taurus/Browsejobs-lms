<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\Support\RouteTicket;
use App\Enums\TicketAuthorType;
use App\Enums\TicketCategory;
use App\Enums\TicketStatus;
use App\Models\CannedResponse;
use App\Models\SupportTeam;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\TicketRoute;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Seeder;

/**
 * Seeds the support desk (PRD §6.13): the concern teams + memberships, the
 * category routing/SLA map from config, a few canned responses, and one demo
 * ticket so the staff workspace is demo-able. Idempotent.
 */
class SupportSeeder extends Seeder
{
    /** @var array<string, array{name: string, category: string, role: string, email: string}> */
    private const TEAMS = [
        'accounts' => ['name' => 'Accounts', 'category' => 'payments', 'role' => 'admin', 'email' => 'accounts@browsejobs.test'],
        'technical' => ['name' => 'Support Agents', 'category' => 'technical', 'role' => 'support-agent', 'email' => 'support@browsejobs.test'],
        'mentorship' => ['name' => 'Counselors', 'category' => 'mentorship', 'role' => 'counselor', 'email' => 'mentor-desk@browsejobs.test'],
        'training' => ['name' => 'Trainers', 'category' => 'training', 'role' => 'trainer', 'email' => 'trainer@browsejobs.test'],
        'interview-prep' => ['name' => 'Placement Officers', 'category' => 'interview_prep', 'role' => 'placement-officer', 'email' => 'placement@browsejobs.test'],
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
        $teams = [];
        foreach (self::TEAMS as $slug => $def) {
            $team = SupportTeam::query()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'slug' => $slug],
                ['name' => $def['name'], 'category' => $def['category']],
            );
            $team->members()->syncWithoutDetaching([$this->staff($tenant, $def)->id]);
            $teams[$slug] = $team;
        }

        foreach ((array) config('support.defaults') as $category => [$routing, $teamSlug, $firstMin, $resMin]) {
            TicketRoute::query()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'category' => $category],
                [
                    'label' => TicketCategory::from($category)->label(),
                    'routing' => $routing,
                    'support_team_id' => $teamSlug !== null ? ($teams[$teamSlug]->id ?? null) : null,
                    'first_response_minutes' => $firstMin,
                    'resolution_minutes' => $resMin,
                    'active' => true,
                ],
            );
        }

        foreach ([
            ['title' => 'Ask for a screenshot', 'body' => 'Thanks for reaching out. Could you share a screenshot of the error you see?'],
            ['title' => 'Payment link resend', 'body' => 'No problem — I have re-sent your payment link. Please check WhatsApp and let me know if it works.'],
            ['title' => 'Resolved — anything else?', 'body' => 'Glad that is sorted. I will resolve this ticket now; reply within 7 days to reopen if you need more help.'],
        ] as $canned) {
            CannedResponse::query()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'title' => $canned['title']],
                ['body' => $canned['body'], 'active' => true],
            );
        }

        $this->demoTicket($tenant);
    }

    private function staff(Tenant $tenant, array $def): User
    {
        $user = User::query()->where('tenant_id', $tenant->id)->where('email', $def['email'])->first();

        if ($user === null) {
            $user = User::factory()->for($tenant)->create([
                'name' => $def['name'].' Staff',
                'email' => $def['email'],
                'user_type' => 'staff',
            ]);
        }

        $user->assignRole($def['role']);

        return $user;
    }

    private function demoTicket(Tenant $tenant): void
    {
        if (Ticket::query()->where('tenant_id', $tenant->id)->exists()) {
            return;
        }

        $student = User::query()->where('tenant_id', $tenant->id)->where('user_type', 'student')->first();
        if ($student === null) {
            return;
        }

        $route = TicketRoute::query()->where('category', TicketCategory::Technical->value)->first();

        $ticket = Ticket::query()->create([
            'tenant_id' => $tenant->id,
            'reference' => 'BJ-PENDING',
            'student_id' => $student->id,
            'category' => TicketCategory::Technical->value,
            'priority' => 'normal',
            'status' => TicketStatus::Open->value,
            'subject' => 'Recordings will not play on my phone',
            'first_response_due_at' => now()->addMinutes($route?->first_response_minutes ?? 240),
            'resolution_due_at' => now()->addMinutes($route?->resolution_minutes ?? 2880),
        ]);
        $ticket->update(['reference' => sprintf('BJ-%06d', $ticket->id)]);

        TicketMessage::query()->create([
            'tenant_id' => $tenant->id,
            'ticket_id' => $ticket->id,
            'author_id' => $student->id,
            'author_type' => TicketAuthorType::Student->value,
            'body' => 'When I tap a recording on my phone it just shows a spinner and never plays. Works on my laptop though.',
        ]);

        app(RouteTicket::class)->handle($ticket);
    }
}
