<?php

declare(strict_types=1);

namespace App\Support\Sms;

/**
 * Transactional SMS sender (PRD §6.9 messaging hub). Provider-switchable via
 * SMS_PROVIDER (msg91 | twilio | blank = log-only). Called only from queued
 * jobs; tests bind a fake.
 */
interface SmsClient
{
    /**
     * Send a message and return the provider message id.
     *
     * @param  string  $to  E.164 phone (digits)
     * @param  string  $body  the rendered body
     */
    public function send(string $to, string $body): string;
}
