<?php

declare(strict_types=1);

use App\Mail\MessageMail;

it('renders the OTP email with a large centered code', function () {
    $html = (new MessageMail(
        'Your BrowseJobs code',
        'Your BrowseJobs code is 482913. It expires in 10 minutes.',
        'otp',
    ))->render();

    expect($html)->toContain('482913')
        ->toContain('Your one-time sign-in code')
        ->toContain('letter-spacing:0.35em') // the big code style
        ->toContain('expires in <strong>10 minutes</strong>');
});

it('renders the batch email with a details card and sign-in button — no password anywhere', function () {
    $body = "Hi Asha, congratulations! Your seat is confirmed and your classes are ready.\n"
        ."Batch: DE-202607-01\n"
        ."Starts: Sat, 01 Aug 2026\n"
        ."Login: asha@example.com\n"
        ."Sign in: http://localhost:3000/student\n"
        .'No password needed — enter your number or email on the sign-in page and we will send you a one-time code (OTP).';

    $html = (new MessageMail('You are in! Batch DE-202607-01 — how to sign in', $body, 'batch_credentials'))->render();

    expect($html)->toContain('asha@example.com')
        ->toContain('DE-202607-01')
        ->toContain('one-time code (OTP)')
        ->toContain('Sign in to your dashboard') // CTA label
        ->toContain('href="http://localhost:3000/student"')
        ->not->toContain('Password') // passwordless everywhere
        ->not->toContain('Sign in: http'); // the raw line became the button
});

it('turns the first URL of a plain message into a call-to-action button', function () {
    $html = (new MessageMail(
        'New class scheduled: SQL Joins',
        'Hi Asha, a new class "SQL Joins" has been scheduled for your batch DE-202607-01 on Mon, 03 Aug 2026 20:00. You can join it from your dashboard: http://localhost:3000/classes.',
        'class_scheduled',
    ))->render();

    expect($html)->toContain('View your classes')
        ->toContain('href="http://localhost:3000/classes"') // trailing "." stays out of the link
        ->not->toContain('href="http://localhost:3000/classes."');
});
