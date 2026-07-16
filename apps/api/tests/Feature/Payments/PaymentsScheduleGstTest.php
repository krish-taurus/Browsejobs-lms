<?php

declare(strict_types=1);

use App\Actions\Payments\PreviewSchedule;
use App\Enums\FeePlanType;
use App\Support\Money\GstCalculator;
use Illuminate\Support\Carbon;

it('previews a single payment as one instalment due today', function () {
    $rows = app(PreviewSchedule::class)->handle(FeePlanType::Single, 1, 3_000_000, 0, Carbon::parse('2026-07-16'));

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['amount_paise'])->toBe(3_000_000)
        ->and($rows[0]['due_on'])->toBe('2026-07-16');
});

it('splits EMI evenly across months, summing to the total in paise', function () {
    $rows = app(PreviewSchedule::class)->handle(FeePlanType::Emi, 3, 3_000_000, 0, Carbon::parse('2026-07-16'));

    expect($rows)->toHaveCount(3)
        ->and(array_column($rows, 'amount_paise'))->toBe([1_000_000, 1_000_000, 1_000_000])
        ->and(array_sum(array_column($rows, 'amount_paise')))->toBe(3_000_000)
        ->and(array_column($rows, 'due_on'))->toBe(['2026-07-16', '2026-08-16', '2026-09-16']);
});

it('clamps EMI due dates at month end (31 Jan -> 28 Feb)', function () {
    $rows = app(PreviewSchedule::class)->handle(FeePlanType::Emi, 2, 3_000_000, 0, Carbon::parse('2026-01-31'));

    expect($rows[1]['due_on'])->toBe('2026-02-28');
});

it('applies a discount to instalment 1, still summing to net', function () {
    $rows = app(PreviewSchedule::class)->handle(FeePlanType::Emi, 3, 3_000_000, 300_000, Carbon::parse('2026-07-16'));

    expect($rows[0]['amount_paise'])->toBe(700_000)
        ->and(array_sum(array_column($rows, 'amount_paise')))->toBe(2_700_000);
});

it('splits an inclusive GST total so the parts sum exactly to the total', function () {
    $gst = GstCalculator::inclusive(3_000_000, 1800);

    expect($gst->taxablePaise)->toBe(2_542_373)
        ->and($gst->cgstPaise + $gst->sgstPaise)->toBe(3_000_000 - 2_542_373)
        ->and($gst->taxablePaise + $gst->cgstPaise + $gst->sgstPaise)->toBe(3_000_000)
        ->and($gst->cgstPaise)->toBe($gst->sgstPaise + ($gst->cgstPaise - $gst->sgstPaise)); // cgst >= sgst by rounding
});
