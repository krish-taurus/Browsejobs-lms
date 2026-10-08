<?php

declare(strict_types=1);

namespace App\Actions\Enquiries;

use App\Jobs\NotifyEnquiry;
use App\Models\Enquiry;
use App\Support\Crm\PhoneNormalizer;
use App\Support\Enquiries\ClientIp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Stores one enquiry and queues the staff email. The raw IP is hashed and dropped.
 */
final class CaptureEnquiry
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data, Request $request): Enquiry
    {
        $type = (string) $data['type'];
        $email = strtolower(trim((string) $data['email']));
        $phone = $this->phone((string) $data['phone']);
        $ip = ClientIp::resolve($request);

        $enquiry = Enquiry::query()->create([
            'type' => $type,
            'status' => 'new',
            'name' => trim((string) $data['name']),
            'email' => trim((string) $data['email']),
            'email_normalized' => $email,
            'phone' => $phone['display'],
            'phone_normalized' => $phone['digits'],
            'company' => $type === Enquiry::TYPE_EMPLOYER ? $this->nullable($data, 'company') : null,
            'company_size' => $type === Enquiry::TYPE_EMPLOYER ? $this->nullable($data, 'company_size') : null,
            'roles' => $type === Enquiry::TYPE_EMPLOYER ? $this->nullable($data, 'roles') : null,
            'timeline' => $type === Enquiry::TYPE_EMPLOYER ? $this->nullable($data, 'timeline') : null,
            'course_slug' => $type === Enquiry::TYPE_COURSE ? $this->nullable($data, 'course_slug') : null,
            'learner_status' => $type === Enquiry::TYPE_COURSE ? $this->nullable($data, 'learner_status') : null,
            'preferred_time' => $type === Enquiry::TYPE_COURSE ? $this->nullable($data, 'preferred_time') : null,
            'city' => $this->nullable($data, 'city'),
            'message' => $this->nullable($data, 'message'),
            'consented_at' => now(),
            'consent_version' => 'v1',
            'utm_source' => $this->nullable($data, 'utm_source'),
            'utm_medium' => $this->nullable($data, 'utm_medium'),
            'utm_campaign' => $this->nullable($data, 'utm_campaign'),
            'referrer' => $this->nullable($data, 'referrer'),
            'landing_page' => $this->nullable($data, 'landing_page'),
            'user_agent' => $this->userAgent($request),
            'ip_hash' => ClientIp::hash($ip),
        ]);

        $this->queueAlert($enquiry);

        return $enquiry;
    }

    /**
     * The row is already stored. A queue outage is recorded on it and does
     * not fail the request. afterCommit waits for an open transaction.
     */
    private function queueAlert(Enquiry $enquiry): void
    {
        try {
            Bus::dispatch((new NotifyEnquiry($enquiry->id))->afterCommit());
        } catch (Throwable $e) {
            Log::warning('Enquiry notification could not be queued', [
                'enquiry_id' => $enquiry->id,
                'message' => $e->getMessage(),
            ]);

            $enquiry->forceFill([
                'notify_error' => mb_substr($e->getMessage() !== '' ? $e->getMessage() : 'Could not queue the notification.', 0, 500),
            ])->save();
        }
    }

    /**
     * @return array{display: string, digits: string}
     */
    private function phone(string $raw): array
    {
        $digits = PhoneNormalizer::normalize($raw);

        if (strlen($digits) === 10) {
            $digits = '91'.$digits;
        }

        $display = str_starts_with(trim($raw), '+') ? '+'.$digits : '+'.$digits;

        return ['display' => $display, 'digits' => $digits];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function nullable(array $data, string $key): ?string
    {
        $value = trim((string) ($data[$key] ?? ''));

        return $value === '' ? null : $value;
    }

    private function userAgent(Request $request): ?string
    {
        $agent = trim((string) $request->userAgent());

        if ($agent === '') {
            return null;
        }

        return mb_substr($agent, 0, 500);
    }
}
