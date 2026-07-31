<?php

declare(strict_types=1);

use App\Actions\Funnel\GenerateWeeklyMasterclasses;
use App\Actions\Funnel\SendBootcampPaymentNudges;
use App\Enums\BatchMemberStatus;
use App\Enums\BatchType;
use App\Models\Attendance;
use App\Models\Batch;
use App\Models\BatchMember;
use App\Models\Course;
use App\Models\Lead;
use App\Models\LiveSession;
use App\Models\Message;
use App\Models\MessageTemplate;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Zoom\FakeZoomClient;
use App\Support\Zoom\ZoomClient;
use Illuminate\Support\Carbon;

beforeEach(function () {
    app()->instance(ZoomClient::class, new FakeZoomClient);
});

function seedFunnelTemplates(Tenant $tenant): void
{
    foreach (['batch_welcome', 'bootcamp_payment_nudge'] as $key) {
        foreach (['whatsapp', 'email'] as $channel) {
            MessageTemplate::query()->create([
                'tenant_id' => $tenant->id, 'key' => $key, 'channel' => $channel,
                'category' => 'utility',
                'subject' => $channel === 'email' ? 'Test '.$key : null,
                'body' => 'Hi {{name}} — {{link}}',
            ]);
        }
    }
}

it('welcomes each seated masterclass student on WhatsApp and email', function () {
    $tenant = Tenant::factory()->create();

    withinTenant($tenant, function () use ($tenant): void {
        seedFunnelTemplates($tenant);
        Course::factory()->for($tenant)->create(['slug' => 'welcome-course', 'code' => 'WC']);

        Lead::query()->create([
            'tenant_id' => $tenant->id, 'lead_type' => 'masterclass', 'name' => 'Welcome Student',
            'phone' => '+919000044441', 'phone_normalized' => '919000044441',
            'email' => 'welcome@example.com', 'course_slug' => 'welcome-course',
            'consented_at' => now(), 'consent_version' => 'v1',
        ]);

        app(GenerateWeeklyMasterclasses::class)->handle();

        $student = User::query()->where('phone', '+919000044441')->first();

        expect($student)->not->toBeNull()
            ->and(Message::query()->where('user_id', $student->id)->where('template_key', 'batch_welcome')->count())
            ->toBe(2); // WhatsApp + email
    });
});

it('nudges bootcamp students for payment after five attended days, exactly once', function () {
    $tenant = Tenant::factory()->create();

    withinTenant($tenant, function () use ($tenant): void {
        seedFunnelTemplates($tenant);
        $course = Course::factory()->for($tenant)->create(['code' => 'NB']);

        $bootcamp = Batch::factory()->for($tenant)->create([
            'course_id' => $course->id, 'type' => BatchType::Bootcamp->value, 'status' => 'active',
        ]);

        $attended = User::factory()->for($tenant)->create(['user_type' => 'student', 'phone' => '+919000044442']);
        $fresh = User::factory()->for($tenant)->create(['user_type' => 'student', 'phone' => '+919000044443']);

        foreach ([$attended, $fresh] as $student) {
            BatchMember::factory()->for($tenant)->create([
                'batch_id' => $bootcamp->id, 'user_id' => $student->id,
                'status' => BatchMemberStatus::Enrolled->value,
            ]);
        }

        foreach (range(1, 5) as $day) {
            $session = LiveSession::query()->create([
                'tenant_id' => $tenant->id, 'batch_id' => $bootcamp->id, 'kind' => 'class',
                'title' => "Day {$day}", 'scheduled_start' => Carbon::now()->subDays(6 - $day),
                'status' => 'ended',
            ]);

            // Only one of the two students attended all five days.
            Attendance::query()->create([
                'tenant_id' => $tenant->id, 'live_session_id' => $session->id,
                'user_id' => $attended->id, 'attended_pct' => 90,
            ]);
        }

        expect(app(SendBootcampPaymentNudges::class)->handle())->toBe(1)
            ->and(Message::query()->where('user_id', $attended->id)->where('template_key', 'bootcamp_payment_nudge')->count())->toBe(2)
            ->and(Message::query()->where('user_id', $fresh->id)->where('template_key', 'bootcamp_payment_nudge')->count())->toBe(0);

        // Second run: already nudged — nothing new.
        expect(app(SendBootcampPaymentNudges::class)->handle())->toBe(0);
    });
});
