<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Enums\OtpChannel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class RequestOtpRequest extends FormRequest
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
            'identifier' => ['required', 'string', 'max:191'],
            'channel' => ['nullable', Rule::enum(OtpChannel::class)],
        ];
    }

    public function channel(): OtpChannel
    {
        $channel = $this->input('channel');
        if (is_string($channel) && $channel !== '') {
            return OtpChannel::from($channel);
        }

        return str_contains((string) $this->input('identifier'), '@')
            ? OtpChannel::Email
            : OtpChannel::Sms;
    }
}
