<?php

declare(strict_types=1);

use App\Jobs\NotifyEnquiry;
use App\Mail\MessageMail;
use App\Models\Enquiry;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Enquiries\ClientIp;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->tenant = Tenant::factory()->domain('acme.test')->create();
});

function enquiryPayload(array $overrides = []): array
{
    return [
        'type' => 'employer',
        'name' => 'Ada North',
        'email' => 'Ada@Example.TEST',
        'phone' => '+91 98400 11111',
        'company' => 'Northwind',
        'company_size' => '11-50',
        'roles' => '2 backend engineers',
        'city' => 'Bengaluru',
        'timeline' => 'this-month',
        'message' => 'We hire every quarter.',
        'consent' => true,
        'utm_source' => 'linkedin',
        'utm_medium' => 'cpc',
        'utm_campaign' => 'hiring-q4',
        'referrer' => 'https://www.linkedin.com/',
        'landing_page' => '/employers/enquire?path=partner',
        'form_started_at' => (int) round(microtime(true) * 1000) - 10_000,
        ...$overrides,
    ];
}

function postEnquiry(array $overrides = [], array $headers = [])
{
    return test()->withHeaders([
        'User-Agent' => 'EnquiryTest/1.0',
        ...$headers,
    ])->postJson('http://acme.test/api/v1/enquiries', enquiryPayload($overrides));
}

it('captures an employer enquiry, normalises contact details, and hashes the ip', function () {
    Mail::fake();

    postEnquiry()->assertCreated()->assertJson(['status' => 'received']);

    $enquiry = Enquiry::withoutGlobalScopes()->first();

    expect($enquiry)->not->toBeNull()
        ->and($enquiry->tenant_id)->toBe($this->tenant->id)
        ->and($enquiry->type)->toBe('employer')
        ->and($enquiry->status)->toBe('new')
        ->and($enquiry->email)->toBe('Ada@Example.TEST')
        ->and($enquiry->email_normalized)->toBe('ada@example.test')
        ->and($enquiry->phone_normalized)->toBe('919840011111')
        ->and($enquiry->company)->toBe('Northwind')
        ->and($enquiry->utm_campaign)->toBe('hiring-q4')
        ->and($enquiry->user_agent)->toBe('EnquiryTest/1.0')
        ->and($enquiry->ip_hash)->toBe(ClientIp::hash('127.0.0.1'))
        ->and($enquiry->consented_at)->not->toBeNull()
        ->and($enquiry->notified_at)->not->toBeNull()
        ->and(array_key_exists('ip', $enquiry->getAttributes()))->toBeFalse();

    Mail::assertSent(MessageMail::class, function (MessageMail $mail) {
        return $mail->hasTo('hello@browsejobs.ai')
            && str_contains($mail->subjectLine, 'Northwind')
            && ! $mail->hasTo('ada@example.test')
            && ! $mail->hasTo('Ada@Example.TEST');
    });
});

it('captures a course enquiry and mails the course inbox', function () {
    Mail::fake();
    config(['enquiry.notify_course' => 'courses@example.test']);

    postEnquiry([
        'type' => 'course',
        'email' => 'learner@example.test',
        'company' => null,
        'company_size' => null,
        'roles' => null,
        'timeline' => null,
        'course_slug' => 'python-backend',
        'learner_status' => 'switcher',
        'preferred_time' => 'evening',
        'city' => 'Pune',
        'landing_page' => '/courses/enquire?course=python-backend',
    ])->assertCreated();

    $enquiry = Enquiry::withoutGlobalScopes()->first();

    expect($enquiry->type)->toBe('course')
        ->and($enquiry->course_slug)->toBe('python-backend')
        ->and($enquiry->company)->toBeNull();

    Mail::assertSent(MessageMail::class, fn (MessageMail $mail) => $mail->hasTo('courses@example.test'));
    Mail::assertNotSent(MessageMail::class, fn (MessageMail $mail) => $mail->hasTo('learner@example.test'));
});

it('captures a counselling callback without a course or a company', function () {
    Mail::fake();

    postEnquiry([
        'type' => 'counselling',
        'email' => 'callback@example.test',
        'company' => null,
        'company_size' => null,
        'roles' => null,
        'timeline' => null,
        'preferred_time' => 'morning',
        'city' => 'Bengaluru',
        'landing_page' => '/students#counselling',
    ])->assertCreated();

    $enquiry = Enquiry::withoutGlobalScopes()->first();

    expect($enquiry->type)->toBe('counselling')
        ->and($enquiry->course_slug)->toBeNull()
        ->and($enquiry->company)->toBeNull()
        ->and($enquiry->preferred_time)->toBe('morning');

    Mail::assertSent(MessageMail::class, fn (MessageMail $mail) => $mail->hasTo('hello@browsejobs.ai')
        && str_contains($mail->subjectLine, 'counselling'));
});

it('rejects a course that is not on the live catalogue', function () {
    postEnquiry([
        'type' => 'course',
        'course_slug' => 'agentic-ai',
        'learner_status' => 'student',
        'preferred_time' => 'morning',
    ])->assertStatus(422);

    expect(Enquiry::withoutGlobalScopes()->count())->toBe(0);
});

it('rejects an enquiry without consent', function () {
    postEnquiry(['consent' => false])->assertStatus(422)->assertJsonValidationErrors('consent');
    expect(Enquiry::withoutGlobalScopes()->count())->toBe(0);
});

it('rejects an employer enquiry that is missing company details', function () {
    postEnquiry(['company' => '', 'roles' => ''])->assertStatus(422)->assertJsonValidationErrors(['company', 'roles']);
    expect(Enquiry::withoutGlobalScopes()->count())->toBe(0);
});

it('rejects a honeypot submission and stores nothing', function () {
    Mail::fake();

    postEnquiry(['website' => 'https://spam.example'])->assertStatus(422);
    expect(Enquiry::withoutGlobalScopes()->count())->toBe(0);
    Mail::assertNothingSent();
});

it('rejects a form that was submitted too quickly', function () {
    postEnquiry([
        'form_started_at' => (int) round(microtime(true) * 1000),
    ])->assertStatus(422)->assertJsonValidationErrors('form');

    expect(Enquiry::withoutGlobalScopes()->count())->toBe(0);
});

it('rejects a form that has expired', function () {
    postEnquiry([
        'form_started_at' => (int) round(microtime(true) * 1000) - 90_000_000,
    ])->assertStatus(422);

    expect(Enquiry::withoutGlobalScopes()->count())->toBe(0);
});

it('rate limits repeated submissions from the same client ip', function () {
    $headers = ['X-Enquiry-Client-Ip' => '203.0.113.77'];

    for ($i = 0; $i < 8; $i++) {
        postEnquiry(['email' => "person{$i}@example.test"], $headers)->assertCreated();
    }

    postEnquiry(['email' => 'blocked@example.test'], $headers)->assertStatus(429);
    expect(Enquiry::withoutGlobalScopes()->count())->toBe(8);
});

it('queues the staff notification', function () {
    Queue::fake();

    postEnquiry()->assertCreated();

    Queue::assertPushed(NotifyEnquiry::class);
    expect(Enquiry::withoutGlobalScopes()->first()->notified_at)->toBeNull();
});

it('sends the staff email once when the job is handled twice', function () {
    Mail::fake();
    $enquiry = Enquiry::factory()->for($this->tenant)->create();

    $job = new NotifyEnquiry($enquiry->id);
    $job->handle();
    $job->handle();

    Mail::assertSent(MessageMail::class, 1);
    expect($enquiry->fresh()->notified_at)->not->toBeNull();
});

it('records a mail failure and still accepts the enquiry', function () {
    Mail::shouldReceive('to')->once()->andReturn(new class
    {
        public function send(mixed $mailable): void
        {
            throw new RuntimeException('Connection refused');
        }
    });

    postEnquiry()->assertCreated()->assertJson(['status' => 'received']);

    $enquiry = Enquiry::withoutGlobalScopes()->first();

    expect($enquiry)->not->toBeNull()
        ->and($enquiry->notified_at)->toBeNull()
        ->and($enquiry->notify_error)->toContain('Connection refused');
});

it('keeps the enquiry when the notification address is unusable', function () {
    config(['enquiry.notify_employer' => 'not-an-email']);

    postEnquiry()->assertCreated();

    $enquiry = Enquiry::withoutGlobalScopes()->first();

    expect($enquiry->notified_at)->toBeNull()
        ->and($enquiry->notify_error)->toBe('Notification address is not a valid email.');
});

it('trusts a forwarded client ip from a same-box private peer without a proxy secret', function () {
    Mail::fake();
    config(['enquiry.proxy_secret' => '']);

    test()->withServerVariables(['REMOTE_ADDR' => '10.0.0.8'])
        ->withHeaders([
            'X-Enquiry-Client-Ip' => '203.0.113.50',
            'User-Agent' => 'EnquiryTest/1.0',
        ])
        ->postJson('http://acme.test/api/v1/enquiries', enquiryPayload())
        ->assertCreated();

    expect(Enquiry::withoutGlobalScopes()->first()->ip_hash)->toBe(ClientIp::hash('203.0.113.50'));
});

it('ignores a spoofed client ip when the peer is not the proxy', function () {
    Mail::fake();

    test()->withServerVariables(['REMOTE_ADDR' => '198.51.100.20'])
        ->withHeaders([
            'X-Enquiry-Client-Ip' => '203.0.113.99',
            'User-Agent' => 'EnquiryTest/1.0',
        ])
        ->postJson('http://acme.test/api/v1/enquiries', enquiryPayload())
        ->assertCreated();

    $enquiry = Enquiry::withoutGlobalScopes()->first();

    expect($enquiry->ip_hash)->toBe(ClientIp::hash('198.51.100.20'))
        ->and($enquiry->ip_hash)->not->toBe(ClientIp::hash('203.0.113.99'));
});

it('scopes enquiries to the resolving tenant', function () {
    Mail::fake();
    $other = Tenant::factory()->domain('other.test')->create();

    postEnquiry()->assertCreated();

    withinTenant($other, function () {
        expect(Enquiry::query()->count())->toBe(0);
    });
    withinTenant($this->tenant, function () {
        expect(Enquiry::query()->count())->toBe(1);
    });
});

it('lists, filters, updates, and exports enquiries for staff in the tenant', function () {
    $this->seed(RolePermissionSeeder::class);
    $admin = User::factory()->for($this->tenant)->create(['user_type' => 'staff']);
    $admin->assignRole('admin');

    $employer = Enquiry::factory()->for($this->tenant)->create([
        'name' => 'Employer One',
        'company' => 'Northwind',
        'notified_at' => null,
        'notify_error' => 'Connection refused',
    ]);
    Enquiry::factory()->for($this->tenant)->course()->create(['name' => 'Learner One']);
    $foreign = Enquiry::factory()->for(Tenant::factory()->create())->create(['name' => 'Foreign One']);

    Sanctum::actingAs($admin);

    $this->getJson('/api/v1/admin/enquiries')
        ->assertOk()
        ->assertJsonFragment(['name' => 'Employer One'])
        ->assertJsonFragment(['notify_error' => 'Connection refused'])
        ->assertJsonFragment(['name' => 'Learner One'])
        ->assertJsonMissing(['name' => 'Foreign One'])
        ->assertJsonMissing(['ip_hash' => $employer->ip_hash]);

    $this->getJson('/api/v1/admin/enquiries?type=course&status=new')
        ->assertOk()
        ->assertJsonFragment(['name' => 'Learner One'])
        ->assertJsonMissing(['name' => 'Employer One']);

    $this->patchJson("/api/v1/admin/enquiries/{$employer->id}", ['status' => 'contacted'])
        ->assertOk()
        ->assertJsonPath('data.status', 'contacted');

    $this->patchJson("/api/v1/admin/enquiries/{$foreign->id}", ['status' => 'closed'])
        ->assertNotFound();

    $csv = $this->get('/api/v1/admin/enquiries/export?type=employer');
    $csv->assertOk();
    expect($csv->headers->get('content-type'))->toContain('text/csv')
        ->and($csv->getContent())->toContain('Employer One')
        ->and($csv->getContent())->toContain($employer->ip_hash)
        ->and($csv->getContent())->toContain('Connection refused')
        ->and($csv->getContent())->not->toContain('Learner One');
});

it('refuses the enquiry inbox to guests and to students', function () {
    $this->seed(RolePermissionSeeder::class);

    $this->getJson('/api/v1/admin/enquiries')->assertUnauthorized();

    $student = User::factory()->for($this->tenant)->create(['user_type' => 'student']);
    Sanctum::actingAs($student);

    $this->getJson('/api/v1/admin/enquiries')->assertForbidden();
});
