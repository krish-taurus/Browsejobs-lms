<?php

declare(strict_types=1);

use App\Models\Course;
use App\Models\InterviewTranscript;
use App\Models\RealInterviewQuestion;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;

/** Interview intelligence: topics ranked by real asked frequency, questions grouped under each. */
beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->tenant = Tenant::factory()->create();
    $admin = User::factory()->for($this->tenant)->create(['user_type' => 'staff']);
    $admin->assignRole('admin');
    Sanctum::actingAs($admin);
});

it('ranks topics by asked frequency with questions and companies grouped under them', function () {
    $course = Course::factory()->for($this->tenant)->create();
    $transcript = InterviewTranscript::query()->create([
        'tenant_id' => $this->tenant->id, 'uploader_id' => 1, 'course_id' => $course->id,
        'source' => 'text', 'company' => 'Infosys', 'status' => 'parsed', 'consent_confirmed_at' => now(),
    ]);
    foreach ([
        ['q' => 'Explain SQL window functions', 'tags' => ['sql'], 'asked' => 5],
        ['q' => 'What is a dict?', 'tags' => ['python'], 'asked' => 2],
        ['q' => 'Write a JOIN across three tables', 'tags' => ['sql'], 'asked' => 3],
    ] as $i => $row) {
        RealInterviewQuestion::query()->create([
            'tenant_id' => $this->tenant->id, 'interview_transcript_id' => $transcript->id,
            'course_id' => $course->id, 'role_title' => 'Data Engineer', 'question' => $row['q'], 'normalized_question' => strtolower($row['q']),
            'fingerprint' => 'fp-'.$i, 'topic_tags' => $row['tags'], 'status' => 'approved', 'asked_count' => $row['asked'],
        ]);
    }
    // Other tenant's bank must never leak in.
    $other = Tenant::factory()->create();
    RealInterviewQuestion::query()->create([
        'tenant_id' => $other->id, 'course_id' => $course->id, 'role_title' => 'Data Engineer', 'question' => 'Leak?',
        'normalized_question' => 'leak', 'fingerprint' => 'fp-x', 'topic_tags' => ['sql'],
        'status' => 'approved', 'asked_count' => 99,
    ]);

    $res = getJson("/api/v1/admin/interview-intel?course_id={$course->id}")->assertOk()->json('data');

    expect($res['sample'])->toBe(3)
        ->and($res['topics'][0]['topic'])->toBe('sql')       // 8 asked beats python's 2
        ->and($res['topics'][0]['frequency'])->toBe(8)
        ->and($res['topics'][0]['items'][0]['question'])->toContain('window functions')
        ->and($res['topics'][0]['items'][0]['company'])->toBe('Infosys')
        ->and($res['companies'][0])->toBe(['company' => 'Infosys', 'frequency' => 10]);
});
