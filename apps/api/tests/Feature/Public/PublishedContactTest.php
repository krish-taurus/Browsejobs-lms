<?php

declare(strict_types=1);

it('keeps the two published phone lines in config', function () {
    expect(config('contact.office.display'))->toBe('+91 79755 54666')
        ->and(config('contact.office.tel'))->toBe('+917975554666')
        ->and(config('contact.office.whatsapp'))->toBe('917975554666')
        ->and(config('contact.employer.display'))->toBe('+91 63634 02404')
        ->and(config('contact.employer.tel'))->toBe('+916363402404')
        ->and(config('contact.employer.whatsapp'))->toBe('916363402404');
});

it('prints the office line on the shared email footer', function () {
    $html = view('emails.message', ['bodyText' => 'Hello'])->render();

    expect($html)->toContain(config('contact.office.display'))
        ->and($html)->not->toContain(config('contact.employer.display'));
});
