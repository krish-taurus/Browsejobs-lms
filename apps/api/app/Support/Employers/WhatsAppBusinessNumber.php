<?php

declare(strict_types=1);

namespace App\Support\Employers;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * The public number of the connected WhatsApp Business account — what the
 * "post jobs on WhatsApp" QR opens a chat with. WHATSAPP_DISPLAY_NUMBER wins
 * when set; otherwise it is read once from Meta (the phone number id's
 * display_phone_number) and cached for a day. Digits only, with country code.
 */
final class WhatsAppBusinessNumber
{
    public function get(): ?string
    {
        $configured = preg_replace('/\D+/', '', (string) config('services.whatsapp.display_number')) ?? '';
        if ($configured !== '') {
            return $configured;
        }

        $phoneId = (string) config('services.whatsapp.phone_number_id');
        $token = (string) config('services.whatsapp.access_token');
        if ($phoneId === '' || $token === '') {
            return null;
        }

        $cached = Cache::get('whatsapp_display_number');
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        try {
            $digits = preg_replace('/\D+/', '', (string) Http::withToken($token)
                ->timeout(10)
                ->get(rtrim((string) config('services.whatsapp.base_url'), '/')."/{$phoneId}", ['fields' => 'display_phone_number'])
                ->json('display_phone_number')) ?? '';
        } catch (Throwable) {
            return null;
        }

        if ($digits === '') {
            return null;
        }

        Cache::put('whatsapp_display_number', $digits, now()->addDay());

        return $digits;
    }
}
