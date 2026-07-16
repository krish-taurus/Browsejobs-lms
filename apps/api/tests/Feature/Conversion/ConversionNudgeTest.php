<?php

declare(strict_types=1);

use App\Actions\Conversion\RunConversionNudges;
use App\Actions\Payments\CreateFeePlan;
use App\Enums\BatchMemberStatus;
use App\Enums\FeePlanType;
use App\Models\BatchMember;
use App\Models\ConversionNudge;
use App\Models\CrmTask;
use App\Models\Instalment;
use App\Models\Lead;
use App\Models\Message;
use App\Models\MessageTemplate;
use App\Models\Tenant;
use App\Support\Crm\PhoneNormalizer;
use App\Support\WhatsApp\FakeWhatsAppClient;
use App\Support\WhatsApp\WhatsAppClient;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->wa = new FakeWhatsAppClient;
    app()->instance(WhatsAppClient::class, $this->wa);
    $this->tenant = Tenant::factory()->create();

    ['batch' => $this->paid, 'student' => $this->student] = reservedMemberIn($this->tenant, '+91 90000 33333', 'attendee@example.com');
    MessageTemplate::factory()->for($this->tenant)->create([
        'key' => 'conversion_nudge', 'channel' => 'whatsapp',
        'body' => 'Hi {{name}}, secure {{batch}} — {{seats}} left, starts {{starts}}: {{link}}',
    ]);
    $this->counselor = counselorIn($this->tenant);
    $this->lead = Lead::factory()->for($this->tenant)->create([
        'phone' => $this->student->phone, 'phone_normalized' => PhoneNormalizer::normalize($this->student->phone),
        'assigned_to' => $this->counselor->id,
    ]);

    $this->plan = withinTenant($this->tenant, function () {
        $plan = app(CreateFeePlan::class)->handle($this->tenant, $this->student, $this->paid, FeePlanType::Single, 1);
        $plan->instalments()->firstOrFail()->update(['payment_link_url' => 'https://rzp.test/l/x']);

        return $plan;
    });
    $this->convertedAt = $this->plan->created_at;
});

it('sends the day-1 nudge once and dedupes on re-run', function () {
    $day1 = $this->convertedAt->copy()->addDay();

    $first = app(RunConversionNudges::class)->handle($day1);
    $second = app(RunConversionNudges::class)->handle($day1);

    expect($first['nudges'])->toBe(1)
        ->and($second['nudges'])->toBe(0)
        ->and(ConversionNudge::withoutGlobalScopes()->where('fee_plan_id', $this->plan->id)->where('rung', 'day-1')->count())->toBe(1);

    $message = Message::withoutGlobalScopes()->where('template_key', 'conversion_nudge')->first();
    expect($message->body)->toContain('https://rzp.test/l/x')
        ->and($this->wa->sent)->toHaveCount(1);
});

it('raises a counselor task at day 3', function () {
    $summary = app(RunConversionNudges::class)->handle($this->convertedAt->copy()->addDays(3));

    expect($summary['tasks'])->toBe(1)
        ->and(CrmTask::withoutGlobalScopes()->where('source', CrmTask::SOURCE_CONVERSION)->where('assigned_to', $this->counselor->id)->count())->toBe(1);
});

it('auto-pauses when the lead replied after conversion', function () {
    $this->lead->update(['last_replied_at' => $this->convertedAt->copy()->addHours(6)]);

    $summary = app(RunConversionNudges::class)->handle($this->convertedAt->copy()->addDay());

    expect($summary['nudges'])->toBe(0)
        ->and(Message::withoutGlobalScopes()->where('template_key', 'conversion_nudge')->count())->toBe(0);
});

it('stops nudging once the member is enrolled (paid)', function () {
    Instalment::withoutGlobalScopes()->where('fee_plan_id', $this->plan->id)->update(['status' => 'paid']);
    BatchMember::withoutGlobalScopes()
        ->where('batch_id', $this->paid->id)->where('user_id', $this->student->id)
        ->update(['status' => BatchMemberStatus::Enrolled->value]);

    $summary = app(RunConversionNudges::class)->handle($this->convertedAt->copy()->addDay());

    expect($summary['nudges'])->toBe(0);
});

it('does not nudge on a non-cadence day', function () {
    $summary = app(RunConversionNudges::class)->handle($this->convertedAt->copy()->addDays(2));

    expect($summary['nudges'])->toBe(0);
});
