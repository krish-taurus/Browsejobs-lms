<?php

declare(strict_types=1);

use App\Support\Learning\Sm2;

it('grows the interval on successive good reviews (1 → 6 → 15)', function () {
    $s1 = Sm2::schedule(2.5, 0, 0, Sm2::qualityFor('good'));
    expect($s1['reps'])->toBe(1)->and($s1['interval_days'])->toBe(1)->and($s1['lapsed'])->toBeFalse();

    $s2 = Sm2::schedule($s1['ease'], $s1['interval_days'], $s1['reps'], Sm2::qualityFor('good'));
    expect($s2['reps'])->toBe(2)->and($s2['interval_days'])->toBe(6);

    $s3 = Sm2::schedule($s2['ease'], $s2['interval_days'], $s2['reps'], Sm2::qualityFor('good'));
    expect($s3['reps'])->toBe(3)->and($s3['interval_days'])->toBe(15); // round(6 * 2.5)
});

it('keeps ease steady on "good" and raises it on "easy"', function () {
    expect(Sm2::schedule(2.5, 0, 0, Sm2::qualityFor('good'))['ease'])->toBe(2.5)
        ->and(Sm2::schedule(2.5, 0, 0, Sm2::qualityFor('easy'))['ease'])->toBe(2.6);
});

it('lapses on "again": resets reps, interval back to 1, ease drops', function () {
    $out = Sm2::schedule(2.5, 15, 3, Sm2::qualityFor('again'));

    expect($out['lapsed'])->toBeTrue()
        ->and($out['reps'])->toBe(0)
        ->and($out['interval_days'])->toBe(1)
        ->and($out['ease'])->toBe(1.96); // 2.5 - 0.54
});

it('floors the ease factor at 1.3', function () {
    $ease = 1.3;
    for ($i = 0; $i < 5; $i++) {
        $ease = Sm2::schedule($ease, 1, 0, Sm2::qualityFor('again'))['ease'];
    }
    expect($ease)->toBe(1.3);
});

it('maps rating words to qualities and rejects unknowns', function () {
    expect(Sm2::qualityFor('again'))->toBe(1)
        ->and(Sm2::qualityFor('easy'))->toBe(5)
        ->and(Sm2::qualityFor('wat'))->toBeNull();
});
