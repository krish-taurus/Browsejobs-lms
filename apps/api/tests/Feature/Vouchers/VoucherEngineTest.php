<?php

declare(strict_types=1);

use App\Actions\Vouchers\ApplyVoucherToFeePlan;
use App\Actions\Vouchers\IssueVoucher;
use App\Actions\Vouchers\ResolveVoucherDiscount;
use App\Enums\VoucherIssueStatus;
use App\Enums\VoucherType;
use App\Models\AuditLog;
use App\Models\Course;
use App\Models\FeePlan;
use App\Models\Tenant;
use App\Models\Testimonial;
use App\Models\User;
use App\Models\Voucher;
use App\Models\VoucherIssue;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->student = User::factory()->for($this->tenant)->create(['user_type' => 'student']);
});

it('issues a voucher with a unique code, expiry and audit', function () {
    $voucher = Voucher::factory()->for($this->tenant)->create(['valid_days' => 30]);

    $issue = withinTenant($this->tenant, fn () => app(IssueVoucher::class)->handle($this->student, $voucher));

    expect($issue->code)->not->toBeEmpty()
        ->and($issue->status)->toBe(VoucherIssueStatus::Issued)
        ->and($issue->expires_at->isFuture())->toBeTrue()
        ->and(AuditLog::withoutGlobalScopes()->where('action', 'voucher.issued')->count())->toBe(1);
});

it('enforces the per-user limit', function () {
    $voucher = Voucher::factory()->for($this->tenant)->create(['per_user_limit' => 1]);

    withinTenant($this->tenant, fn () => app(IssueVoucher::class)->handle($this->student, $voucher));

    expect(fn () => withinTenant($this->tenant, fn () => app(IssueVoucher::class)->handle($this->student, $voucher)))
        ->toThrow(ValidationException::class);
});

it('is idempotent per testimonial', function () {
    $voucher = Voucher::factory()->for($this->tenant)->create();
    $testimonial = Testimonial::factory()->for($this->tenant)->create(['user_id' => $this->student->id]);

    $first = withinTenant($this->tenant, fn () => app(IssueVoucher::class)->handle($this->student, $voucher, $testimonial));
    $testimonial->update(['voucher_issue_id' => $first->id]);
    $second = withinTenant($this->tenant, fn () => app(IssueVoucher::class)->handle($this->student, $voucher, $testimonial->fresh()));

    expect($second->id)->toBe($first->id)
        ->and(VoucherIssue::withoutGlobalScopes()->count())->toBe(1);
});

it('resolves a flat discount for the student and batch', function () {
    ['batch' => $batch, 'student' => $student] = reservedMemberIn($this->tenant);
    $voucher = Voucher::factory()->for($this->tenant)->create(['type' => VoucherType::Flat->value, 'value' => 200_000]);
    VoucherIssue::factory()->for($this->tenant)->create(['voucher_id' => $voucher->id, 'user_id' => $student->id]);

    $resolved = withinTenant($this->tenant, fn () => app(ResolveVoucherDiscount::class)->handle($student, $batch));

    expect($resolved)->not->toBeNull()
        ->and($resolved['discount_paise'])->toBe(200_000);
});

it('resolves a percent discount and honours the cap', function () {
    ['batch' => $batch, 'student' => $student] = reservedMemberIn($this->tenant);
    // 20% of ₹30,000 = ₹6,000, capped at ₹2,500.
    $voucher = Voucher::factory()->for($this->tenant)->create([
        'type' => VoucherType::Percent->value, 'value' => 2_000, 'max_discount_paise' => 250_000,
    ]);
    VoucherIssue::factory()->for($this->tenant)->create(['voucher_id' => $voucher->id, 'user_id' => $student->id]);

    $resolved = withinTenant($this->tenant, fn () => app(ResolveVoucherDiscount::class)->handle($student, $batch));

    expect($resolved['discount_paise'])->toBe(250_000);
});

it('does not resolve a voucher scoped to a different course', function () {
    ['batch' => $batch, 'student' => $student] = reservedMemberIn($this->tenant);
    $otherCourse = Course::factory()->for($this->tenant)->create();
    $voucher = Voucher::factory()->for($this->tenant)->create(['course_id' => $otherCourse->id]);
    VoucherIssue::factory()->for($this->tenant)->create(['voucher_id' => $voucher->id, 'user_id' => $student->id]);

    $resolved = withinTenant($this->tenant, fn () => app(ResolveVoucherDiscount::class)->handle($student, $batch));

    expect($resolved)->toBeNull();
});

it('applies a voucher to a fee plan and blocks a second when stacking is off', function () {
    $feePlan = FeePlan::factory()->for($this->tenant)->create();
    $voucher = Voucher::factory()->for($this->tenant)->create(['allow_stacking' => false]);
    $issueA = VoucherIssue::factory()->for($this->tenant)->create(['voucher_id' => $voucher->id, 'user_id' => $this->student->id]);
    $issueB = VoucherIssue::factory()->for($this->tenant)->create(['voucher_id' => $voucher->id, 'user_id' => $this->student->id]);

    withinTenant($this->tenant, fn () => app(ApplyVoucherToFeePlan::class)->handle($issueA->fresh()->load('voucher'), $feePlan, 200_000));

    expect($issueA->fresh()->status)->toBe(VoucherIssueStatus::Applied)
        ->and($issueA->fresh()->fee_plan_id)->toBe($feePlan->id)
        ->and($issueA->fresh()->discount_paise)->toBe(200_000);

    expect(fn () => withinTenant($this->tenant, fn () => app(ApplyVoucherToFeePlan::class)->handle($issueB->fresh()->load('voucher'), $feePlan, 200_000)))
        ->toThrow(ValidationException::class);
});
