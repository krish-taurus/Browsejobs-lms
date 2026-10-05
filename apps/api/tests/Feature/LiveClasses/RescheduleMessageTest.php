<?php

declare(strict_types=1);

use App\Actions\LiveClasses\ArmSessionReminders;
use App\Actions\LiveClasses\RescheduleLiveSession;
use App\Enums\BatchType;
use App\Enums\LiveSessionStatus;
use App\Models\BatchMember;
use App\Models\Course;
use App\Models\LiveSession;
use App\Models\Message;
use App\Models\MessageTemplate;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Notifications\SessionNotifier;
use App\Support\WhatsApp\FakeWhatsAppClient;
use App\Support\WhatsApp\WhatsAppClient;
use Database\Seeders\MessagingSeeder;
use Illuminate\Support\Facades\Queue;

/**
 * The student's reschedule message must carry the same facts the trainer's
 * does — which batch, the old slot and the new slot. "Was rescheduled" with no
 * time left students with nothing to act on.
 */
beforeEach(function () {
    Queue::fake();
    app()->instance(WhatsAppClient::class, new FakeWhatsAppClient);

    // MessagingSeeder seeds the browsejobs tenant by slug.
    $this->tenant = Tenant::factory()->create(['slug' => 'browsejobs']);
    $this->seed(MessagingSeeder::class);

    $this->student = User::factory()->for($this->tenant)->create([
        'user_type' => 'student', 'name' => 'Anshu', 'phone' => '+91 90000 12345',
    ]);

    $this->session = withinTenant($this->tenant, function () {
        $course = Course::query()->create(['code' => 'DE', 'name' => 'Data Engineering', 'slug' => 'de']);
        $batch = $course->batches()->create(['number' => 'DE-202608-102', 'type' => BatchType::Bootcamp->value]);
        BatchMember::query()->create(['batch_id' => $batch->id, 'user_id' => $this->student->id, 'status' => 'enrolled']);

        return LiveSession::query()->create([
            'batch_id' => $batch->id, 'title' => 'comms class',
            'scheduled_start' => now()->addDays(2)->setTime(16, 15),
            'status' => LiveSessionStatus::Scheduled->value, 'reminder_token' => 'original',
        ]);
    });
});

it('gives the student the new date and time in the agreed layout', function () {
    $newStart = now()->addDays(3)->setTime(17, 2);

    withinTenant($this->tenant, fn () => app(RescheduleLiveSession::class)
        ->handle($this->session, $newStart, $newStart->copy()->addMinutes(90), 'Technical issue'));

    $message = Message::withoutGlobalScopes()
        ->where('user_id', $this->student->id)
        ->where('template_key', 'class_rescheduled')
        ->where('channel', 'whatsapp')
        ->sole();

    expect($message->body)->toBe(
        "📅 *Class Rescheduled – BrowseJobs*\n\n"
        ."Hi Anshu,\n\n"
        ."Your class \"comms class\" for Data Engineering | Batch DE-202608-102 has been rescheduled.\n\n"
        ."*Updated Schedule:*\n"
        .'📅 '.$newStart->format('j F Y')."\n"
        .'🕔 '.$newStart->format('g:i A')."\n\n"
        ."Please make a note of the updated schedule and join the class at the revised time.\n\n"
        .'— Team BrowseJobs'
    );
});

it('says nothing when the class is saved back onto the slot it already had', function () {
    $sameStart = $this->session->scheduled_start->copy();

    withinTenant($this->tenant, fn () => app(RescheduleLiveSession::class)
        ->handle($this->session, $sameStart, $sameStart->copy()->addMinutes(90), 'Double click'));

    expect(Message::withoutGlobalScopes()->where('template_key', 'class_rescheduled')->count())->toBe(0);
});

it('messages each student exactly once per real reschedule', function () {
    withinTenant($this->tenant, function () {
        app(RescheduleLiveSession::class)->handle($this->session, now()->addDays(3)->setTime(17, 0), null, 'First');
        // A second save of the SAME slot — the double-click case.
        app(RescheduleLiveSession::class)->handle($this->session->refresh(), now()->addDays(3)->setTime(17, 0), null, 'First');
    });

    expect(Message::withoutGlobalScopes()
        ->where('template_key', 'class_rescheduled')
        ->where('user_id', $this->student->id)
        ->where('channel', 'whatsapp')
        ->count())->toBe(1);
});

it('keeps the 5-minute rung on the approved generic reminder until Meta approves its own', function () {
    withinTenant($this->tenant, fn () => app(ArmSessionReminders::class)->handle($this->session));

    $magic = 'https://example.test/join';
    withinTenant($this->tenant, fn () => app(SessionNotifier::class)
        ->reminder($this->student, $this->session, $magic, '5min'));

    // No approved Meta template for class_reminder_5min yet → generic key, which
    // is the one that actually delivers.
    expect(Message::withoutGlobalScopes()->where('template_key', 'class_reminder_5min')->exists())->toBeFalse()
        ->and(Message::withoutGlobalScopes()->where('template_key', 'class_reminder')->exists())->toBeTrue();
});

it('switches the 5-minute rung to its own copy the moment Meta approves it', function () {
    MessageTemplate::withoutGlobalScopes()
        ->where('key', 'class_reminder_5min')->where('channel', 'whatsapp')
        ->update(['name' => 'bj_class_starting_5min']);

    withinTenant($this->tenant, fn () => app(SessionNotifier::class)
        ->reminder($this->student, $this->session, 'https://example.test/join', '5min'));

    $message = Message::withoutGlobalScopes()
        ->where('template_key', 'class_reminder_5min')->where('channel', 'whatsapp')->sole();

    expect($message->body)->toBe(
        "⏰ *Your Class Starts in 5 Minutes!*\n\n"
        ."Hi Anshu,\n\n"
        ."Your comms class class for Data Engineering | Batch DE-202608-102 is starting in 5 minutes.\n\n"
        .'🕐 Start Time: '.$this->session->scheduled_start->format('g:i A')."\n\n"
        ."Please get ready and join the class on time.\n\n"
        // The magic link passed in is deliberately ignored on this rung.
        ."👉 Join Class: https://browsejobs.ai/classes\n\n"
        ."See you in class!\n\n"
        .'— Team BrowseJobs'
    );
});
