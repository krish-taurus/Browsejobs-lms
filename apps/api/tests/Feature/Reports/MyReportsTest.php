<?php

declare(strict_types=1);

use App\Actions\Auth\ConsumeMagicLink;
use App\Enums\ReportType;
use App\Models\Report;
use App\Models\Tenant;
use App\Models\User;
use App\Support\MagicLink\MagicLinkService;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->student = User::factory()->for($this->tenant)->create(['user_type' => 'student']);
});

it('lists and shows the student their own weekly report, marking it read', function () {
    $report = Report::factory()->for($this->tenant)->create(['recipient_id' => $this->student->id, 'type' => ReportType::WeeklyStudent->value]);
    Sanctum::actingAs($this->student);

    getJson('/api/v1/me/reports')->assertOk()->assertJsonPath('data.0.id', $report->id);

    getJson("/api/v1/me/reports/{$report->id}")->assertOk()->assertJsonPath('data.narrative', $report->narrative);
    expect($report->fresh()->read_at)->not->toBeNull();
});

it('rejects reports for guests and other students', function () {
    $report = Report::factory()->for($this->tenant)->create(['recipient_id' => $this->student->id, 'type' => ReportType::WeeklyStudent->value]);

    getJson("/api/v1/me/reports/{$report->id}")->assertUnauthorized();

    $intruder = User::factory()->for(Tenant::factory()->create())->create(['user_type' => 'student']);
    Sanctum::actingAs($intruder);
    getJson("/api/v1/me/reports/{$report->id}")->assertNotFound();
});

it('does not expose a staff brief/digest through the student endpoint', function () {
    $brief = Report::factory()->for($this->tenant)->trainerBrief()->create(['recipient_id' => $this->student->id]);
    Sanctum::actingAs($this->student);

    getJson('/api/v1/me/reports')->assertOk()->assertJsonCount(0, 'data');
    getJson("/api/v1/me/reports/{$brief->id}")->assertNotFound();
});

it('lands the student on their report via the magic link', function () {
    $report = Report::factory()->for($this->tenant)->create(['recipient_id' => $this->student->id, 'type' => ReportType::WeeklyStudent->value]);

    $url = app(MagicLinkService::class)->create(
        $this->tenant, 'report.view', $this->student,
        ['report_id' => $report->id, 'redirect' => '/reports/'.$report->id],
    );
    $token = (string) str($url)->after('/magic/')->before('?');

    $link = app(ConsumeMagicLink::class)->handle($token);

    expect($link->user_id)->toBe($this->student->id)
        ->and($link->payload['redirect'])->toBe('/reports/'.$report->id);
});
