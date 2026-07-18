<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Enums\OtpChannel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'min:8', 'max:20'],
            'email' => ['nullable', 'email', 'max:191'],
            'channel' => ['nullable', Rule::enum(OtpChannel::class)],
            // DPDP: explicit consent (incl. activity-monitoring telemetry disclosure)
            // is a prerequisite to creating the account (PRD §10 / audit gap #7).
            'consent' => ['accepted'],
        ];
    }

    /** The identifier the OTP goes to: email when chosen and given, else phone. */
    public function identifier(): string
    {
        $channel = $this->channel();

        return $channel === OtpChannel::Email
            ? $this->string('email')->toString()
            : $this->string('phone')->toString();
    }

    public function channel(): OtpChannel
    {
        $channel = $this->input('channel');
        if (is_string($channel) && $channel !== '') {
            return OtpChannel::from($channel);
        }

        return OtpChannel::Sms;
    }
}
