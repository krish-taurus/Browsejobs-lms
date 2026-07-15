<?php

declare(strict_types=1);

use App\Actions\Batches\CreateBatch;
use App\Enums\BatchType;
use App\Models\Course;
use App\Models\Tenant;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->course = withinTenant($this->tenant, fn () => Course::query()->create([
        'code' => 'DE', 'name' => 'Data Engineering', 'slug' => 'de',
    ]));
});

it('auto-generates the {COURSE}-{YYYYMM}-{seq} number and increments it', function () {
    $this->travelTo(Carbon::parse('2026-08-15'));

    $first = app(CreateBatch::class)->handle($this->course, BatchType::Bootcamp);
    $second = app(CreateBatch::class)->handle($this->course, BatchType::Bootcamp);

    expect($first->number)->toBe('DE-202608-01')
        ->and($second->number)->toBe('DE-202608-02');
});

it('accepts a manual number override', function () {
    $batch = app(CreateBatch::class)->handle($this->course, BatchType::Paid, manualNumber: 'DE-SPECIAL-01');

    expect($batch->number)->toBe('DE-SPECIAL-01');
});

it('rejects a duplicate batch number within a tenant', function () {
    app(CreateBatch::class)->handle($this->course, BatchType::Paid, manualNumber: 'DE-DUP-01');

    expect(fn () => app(CreateBatch::class)->handle($this->course, BatchType::Paid, manualNumber: 'DE-DUP-01'))
        ->toThrow(ValidationException::class);
});

it('lets two tenants reuse the same batch number', function () {
    app(CreateBatch::class)->handle($this->course, BatchType::Paid, manualNumber: 'DE-202608-01');

    $otherTenant = Tenant::factory()->create();
    $otherCourse = withinTenant($otherTenant, fn () => Course::query()->create([
        'code' => 'DE', 'name' => 'Data Engineering', 'slug' => 'de',
    ]));

    $batch = app(CreateBatch::class)->handle($otherCourse, BatchType::Paid, manualNumber: 'DE-202608-01');

    expect($batch->number)->toBe('DE-202608-01');
});
