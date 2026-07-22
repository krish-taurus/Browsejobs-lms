<?php

declare(strict_types=1);

use App\Actions\LiveClasses\RunClassWrapups;
use App\Enums\BatchType;
use App\Enums\LiveSessionStatus;
use App\Models\BatchMember;
use App\Models\Course;
use App\Models\Flashcard;
use App\Models\InAppNotification;
use App\Models\Lesson;
use App\Models\LiveSession;
use App\Models\Message;
use App\Models\MessageTemplate;
use App\Models\Module;
use App\Models\Tenant;
use App\Models\Topic;
use App\Models\User;
use App\Support\WhatsApp\FakeWhatsAppClient;
use App\Support\WhatsApp\WhatsAppClient;
use Carbon\CarbonInterface;

beforeEach(function () {
    config(['app.frontend_url' => 'https://browsejobs.ai']);
    app()->instance(WhatsAppClient::class, new FakeWhatsAppClient);
    $this->tenant = Tenant::factory()->create();

    foreach ([
        ['key' => 'class_wrapup', 'channel' => 'whatsapp', 'subject' => null, 'body' => 'Hi {{name}}, flashcards for "{{title}}" are ready: {{link}}'],
        ['key' => 'class_wrapup', 'channel' => 'email', 'subject' => 'Review {{title}}', 'body' => 'Hi {{name}}, review "{{title}}": {{link}}'],
    ] as $t) {
        MessageTemplate::factory()->for($this->tenant)->create($t);
    }
});

/**
 * A batch with a topic, an ended class on that topic, and (optionally) a
 * published flashcard for the topic's lesson.
 *
 * @return array{0: LiveSession, 1: Topic, 2: Lesson}
 */
function wrapupFixture(Tenant $tenant, bool $withFlashcards = true, ?CarbonInterface $start = null): array
{
    return withinTenant($tenant, function () use ($tenant, $withFlashcards, $start) {
        $course = Course::factory()->for($tenant)->create();
        $batch = $course->batches()->create(['number' => 'DE-1', 'type' => BatchType::Paid->value]);
        $module = Module::query()->create(['tenant_id' => $tenant->id, 'course_id' => $course->id, 'name' => 'M1', 'position' => 0]);
        $topic = Topic::query()->create(['tenant_id' => $tenant->id, 'module_id' => $module->id, 'name' => 'Recursion', 'position' => 0]);
        $lesson = Lesson::factory()->for($tenant)->create(['topic_id' => $topic->id, 'type' => 'notes', 'title' => 'Recursion notes']);

        if ($withFlashcards) {
            Flashcard::query()->create(['tenant_id' => $tenant->id, 'lesson_id' => $lesson->id, 'front' => 'q', 'back' => 'a', 'position' => 0]);
        }

        $session = LiveSession::query()->create([
            'tenant_id' => $tenant->id, 'batch_id' => $batch->id, 'topic_id' => $topic->id, 'title' => 'Recursion class',
            'scheduled_start' => $start ?? now()->subHours(3), 'status' => LiveSessionStatus::Ended->value,
        ]);

        return [$session, $topic, $lesson];
    });
}

function wrapEnrol(Tenant $tenant, int $batchId, string $status = 'enrolled'): User
{
    $student = User::factory()->for($tenant)->create(['user_type' => 'student', 'phone' => '+91 90000 '.random_int(10000, 99999)]);
    withinTenant($tenant, fn () => BatchMember::query()->create([
        'tenant_id' => $tenant->id, 'batch_id' => $batchId, 'user_id' => $student->id, 'status' => $status,
    ]));

    return $student;
}

it('nudges the whole cohort once flashcards are published, and marks the class wrapped', function () {
    [$session, , $lesson] = wrapupFixture($this->tenant);
    $present = wrapEnrol($this->tenant, $session->batch_id);
    $absent = wrapEnrol($this->tenant, $session->batch_id); // absentees are nudged too — that's their catch-up

    expect(app(RunClassWrapups::class)->handle())->toBe(1);

    foreach ([$present, $absent] as $student) {
        expect(Message::withoutGlobalScopes()->where('template_key', 'class_wrapup')->where('recipient', $student->phone)->exists())->toBeTrue()
            ->and(InAppNotification::withoutGlobalScopes()->where('user_id', $student->id)->where('url', "/flashcards/{$lesson->id}")->exists())->toBeTrue();
    }

    // Message deep-links to this class's flashcards.
    expect(Message::withoutGlobalScopes()->where('template_key', 'class_wrapup')->where('channel', 'whatsapp')->first()->body)
        ->toContain("https://browsejobs.ai/flashcards/{$lesson->id}");
    expect($session->fresh()->wrapped_up_at)->not->toBeNull();
});

it('excludes members who are no longer occupying a seat', function () {
    [$session] = wrapupFixture($this->tenant);
    $dropped = wrapEnrol($this->tenant, $session->batch_id, 'dropped');
    wrapEnrol($this->tenant, $session->batch_id); // one real member so the class still wraps

    app(RunClassWrapups::class)->handle();

    expect(Message::withoutGlobalScopes()->where('recipient', $dropped->phone)->exists())->toBeFalse();
});

it('is idempotent — a wrapped class is never nudged twice', function () {
    [$session] = wrapupFixture($this->tenant);
    wrapEnrol($this->tenant, $session->batch_id);

    expect(app(RunClassWrapups::class)->handle())->toBe(1)
        ->and(app(RunClassWrapups::class)->handle())->toBe(0);

    expect(Message::withoutGlobalScopes()->where('template_key', 'class_wrapup')->count())->toBe(1);
});

it('waits — does not wrap a class whose flashcards are not published yet', function () {
    [$session] = wrapupFixture($this->tenant, withFlashcards: false);
    wrapEnrol($this->tenant, $session->batch_id);

    expect(app(RunClassWrapups::class)->handle())->toBe(0);
    expect($session->fresh()->wrapped_up_at)->toBeNull()
        ->and(Message::withoutGlobalScopes()->where('template_key', 'class_wrapup')->count())->toBe(0);
});

it('does not wrap a class that only just ended (within the grace window)', function () {
    [$session] = wrapupFixture($this->tenant, start: now()->subMinutes(10));
    wrapEnrol($this->tenant, $session->batch_id);

    expect(app(RunClassWrapups::class)->handle())->toBe(0);
    expect($session->fresh()->wrapped_up_at)->toBeNull();
});

it('never nudges across tenants (audience is the class\'s own cohort)', function () {
    [$sessionA] = wrapupFixture($this->tenant);
    $mine = wrapEnrol($this->tenant, $sessionA->batch_id);

    $other = Tenant::factory()->create();
    [$sessionB] = wrapupFixture($other);
    $theirs = wrapEnrol($other, $sessionB->batch_id);

    app(RunClassWrapups::class)->handle();

    // Each student is nudged only for their own tenant's class.
    expect(Message::withoutGlobalScopes()->where('recipient', $mine->phone)->where('tenant_id', $this->tenant->id)->exists())->toBeTrue()
        ->and(Message::withoutGlobalScopes()->where('recipient', $mine->phone)->where('tenant_id', $other->id)->exists())->toBeFalse()
        ->and(Message::withoutGlobalScopes()->where('recipient', $theirs->phone)->where('tenant_id', $this->tenant->id)->exists())->toBeFalse();
});
