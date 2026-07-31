<?php

declare(strict_types=1);

use App\Actions\Funnel\RolloverMasterclassesToBootcamps;
use App\Enums\BatchMemberStatus;
use App\Enums\BatchType;
use App\Models\Batch;
use App\Models\BatchMember;
use App\Models\Course;
use App\Models\Message;
use App\Models\MessageTemplate;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Zoom\FakeZoomClient;
use App\Support\Zoom\ZoomClient;

beforeEach(function () {
    app()->instance(ZoomClient::class, new FakeZoomClient);
});

function seedCredentialTemplates(Tenant $tenant): void
{
    foreach (['batch_welcome', 'batch_credentials'] as $key) {
        foreach (['whatsapp', 'email'] as $channel) {
            MessageTemplate::query()->create([
                'tenant_id' => $tenant->id, 'key' => $key, 'channel' => $channel,
                'category' => 'utility',
                'subject' => $channel === 'email' ? 'Test '.$key : null,
                'body' => 'Hi {{name}} — batch {{batch}}'.($key === 'batch_credentials' ? ' sign in at {{link}} with {{login}}' : ''),
            ]);
        }
    }
}

it('sends OTP sign-in instructions (no password) when a masterclass rolls into the bootcamp', function () {
    $tenant = Tenant::factory()->create();

    ['masterclass' => $masterclass, 'student' => $student] = withinTenant($tenant, function () use ($tenant): array {
        seedCredentialTemplates($tenant);
        $course = Course::factory()->for($tenant)->create();

        $masterclass = Batch::factory()->for($tenant)->create([
            'course_id' => $course->id, 'type' => BatchType::Masterclass->value,
            'status' => 'active', 'starts_on' => now()->subDay()->toDateString(),
            'ends_on' => now()->subDay()->toDateString(),
        ]);

        $student = User::factory()->for($tenant)->create([
            'user_type' => 'student', 'phone' => '+919000055551',
            'email' => 'grad@example.com', 'password' => null,
        ]);

        BatchMember::factory()->for($tenant)->create([
            'batch_id' => $masterclass->id, 'user_id' => $student->id,
            'status' => BatchMemberStatus::Enrolled->value,
        ]);

        return compact('masterclass', 'student');
    });

    withinTenant($tenant, function () use ($student): void {
        app(RolloverMasterclassesToBootcamps::class)->handle();

        $bootcamp = Batch::query()->where('type', BatchType::Bootcamp->value)->first();
        $messages = Message::query()
            ->where('user_id', $student->id)
            ->where('template_key', 'batch_credentials')
            ->get();

        // Passwordless: no password is ever generated, the message carries the
        // batch number + OTP sign-in instructions on both channels.
        expect($student->fresh()->password)->toBeNull()
            ->and($bootcamp)->not->toBeNull()
            ->and($messages)->toHaveCount(2) // WhatsApp + email
            ->and($messages->first()->body)->toContain('batch '.$bootcamp->number);
    });
});
