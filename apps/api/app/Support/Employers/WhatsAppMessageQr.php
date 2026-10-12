<?php

declare(strict_types=1);

namespace App\Support\Employers;

use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Meta's "message QR codes" for the business number (Cloud API
 * message_qrdls). Unlike a plain wa.me link, these open from WhatsApp's own
 * in-app scanner. Best-effort: any failure returns null and callers fall
 * back to the wa.me link, which still works from the phone camera.
 */
final class WhatsAppMessageQr
{
    /** @return array{code: string, deep_link: string}|null */
    public function create(string $prefilledMessage): ?array
    {
        $endpoint = $this->endpoint();
        if ($endpoint === null) {
            return null;
        }

        try {
            $response = Http::withToken((string) config('services.whatsapp.access_token'))
                ->timeout(10)
                ->post($endpoint, ['prefilled_message' => $prefilledMessage, 'generate_qr_image' => 'SVG']);
        } catch (Throwable) {
            return null;
        }

        $code = $response->json('code');
        $link = $response->json('deep_link_url');

        return $response->successful() && is_string($code) && is_string($link) && str_starts_with($link, 'https://')
            ? ['code' => $code, 'deep_link' => $link]
            : null;
    }

    public function delete(string $code): void
    {
        $endpoint = $this->endpoint();
        if ($endpoint === null) {
            return;
        }

        try {
            Http::withToken((string) config('services.whatsapp.access_token'))
                ->timeout(10)
                ->delete($endpoint.'/'.rawurlencode($code));
        } catch (Throwable) {
            // An orphaned QR on Meta's side is harmless: its JOIN code no longer exists here.
        }
    }

    private function endpoint(): ?string
    {
        $phoneId = (string) config('services.whatsapp.phone_number_id');
        $token = (string) config('services.whatsapp.access_token');

        return $phoneId === '' || $token === ''
            ? null
            : rtrim((string) config('services.whatsapp.base_url'), '/')."/{$phoneId}/message_qrdls";
    }
}
