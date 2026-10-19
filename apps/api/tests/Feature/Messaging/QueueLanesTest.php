<?php

declare(strict_types=1);

use App\Actions\Auth\RequestOtp;
use App\Actions\Reports\RunWeeklyReports;
use App\Enums\BatchMemberStatus;
use App\Enums\MessageChannel;
use App\Enums\OtpChannel;
use App\Enums\OtpPurpose;
use App\Jobs\GenerateWeeklyReportJob;
use App\Jobs\SendEmailMessage;
use App\Jobs\SendWhatsAppMessage;
use App\Models\Batch;
use App\Models\BatchMember;
use App\Models\MessageTemplate;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Messaging\Messenger;
use App\Support\Queues;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    Queue::fake();
    $this->tenant = Tenant::factory()->create();
});

it('puts a login code on the fast lane so it never waits behind a bulk send', function (): void {
    MessageTemplate::factory()->for($this->tenant)->create(['key' => 'otp', 'channel' => 'whatsapp', 'body' => 'Your code is {{code}}.']);

    withinTenant($this->tenant, fn () => app(RequestOtp::class)->handle($this->tenant, '+91 90000 55555', OtpChannel::Sms, OtpPurpose::Login));

    Queue::assertPushedOn(Queues::HIGH, SendWhatsAppMessage::class);
    expect(Queue::pushed(SendWhatsAppMessage::class)->pluck('queue')->unique()->all())->toBe([Queues::HIGH]);
});

it('puts an emailed login code on the fast lane too', function (): void {
    MessageTemplate::factory()->for($this->tenant)->create(['key' => 'otp', 'channel' => 'email', 'body' => 'Your code is {{code}}.']);

    withinTenant($this->tenant, fn () => app(Messenger::class)->sendRaw($this->tenant->id, MessageChannel::Email, 'student@example.com', 'otp', ['code' => '123456']));

    Queue::assertPushedOn(Queues::HIGH, SendEmailMessage::class);
});

it('leaves every other message on the normal lane', function (): void {
    MessageTemplate::factory()->for($this->tenant)->create(['key' => 'weekly_report', 'channel' => 'whatsapp', 'body' => 'Your week: {{summary}}']);

    withinTenant($this->tenant, fn () => app(Messenger::class)->sendRaw($this->tenant->id, MessageChannel::WhatsApp, '+91 90000 66666', 'weekly_report', ['summary' => 'ok']));

    Queue::assertPushedOn(Queues::DEFAULT, SendWhatsAppMessage::class);
    expect(Queue::pushed(SendWhatsAppMessage::class)->pluck('queue')->unique()->all())->toBe([Queues::DEFAULT]);
});

it('runs the weekly reports on their own worker, not the queue login codes use', function (): void {
    $batch = Batch::factory()->for($this->tenant)->create();
    $student = User::factory()->for($this->tenant)->create(['user_type' => 'student']);
    BatchMember::factory()->for($this->tenant)->create(['batch_id' => $batch->id, 'user_id' => $student->id, 'status' => BatchMemberStatus::Enrolled->value]);

    $summary = app(RunWeeklyReports::class)->handle();

    expect($summary['queued'])->toBe(1);
    Queue::assertPushedOn(Queues::BULK, GenerateWeeklyReportJob::class);
    expect(Queue::pushed(GenerateWeeklyReportJob::class)->pluck('queue')->unique()->all())->toBe([Queues::BULK]);
});

it('knows which messages a person is waiting for', function (): void {
    expect(Queues::forTemplate('otp'))->toBe(Queues::HIGH)
        ->and(Queues::forTemplate('weekly_report'))->toBe(Queues::DEFAULT)
        ->and(Queues::forTemplate(null))->toBe(Queues::DEFAULT);
});
