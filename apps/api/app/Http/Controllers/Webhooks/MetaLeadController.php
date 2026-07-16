<?php

declare(strict_types=1);

namespace App\Http\Controllers\Webhooks;

use App\Actions\Crm\CaptureLead;
use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Meta Lead Ads webhook (PRD §6.12 "instant, no Zapier"). The GET request is the
 * Graph API subscription handshake (echo hub.challenge when the verify token
 * matches). The POST delivers leadgen changes — signature already verified by
 * `meta.signed` middleware — which map straight onto CaptureLead.
 *
 * P2.1 reads inline `field_data` from the change payload. Payloads that carry
 * only a `leadgen_id` (requiring a Graph API fetch) are acknowledged and skipped
 * — that fetch is a later enhancement (see ADR 0006).
 */
final class MetaLeadController extends Controller
{
    public function verify(Request $request): Response
    {
        $mode = (string) $request->query('hub_mode', (string) $request->query('hub.mode', ''));
        $token = (string) $request->query('hub_verify_token', (string) $request->query('hub.verify_token', ''));
        $challenge = (string) $request->query('hub_challenge', (string) $request->query('hub.challenge', ''));
        $expected = (string) config('services.meta.verify_token');

        if ($mode === 'subscribe' && $expected !== '' && hash_equals($expected, $token)) {
            return response($challenge, 200)->header('Content-Type', 'text/plain');
        }

        abort(403, 'Meta verification failed.');
    }

    public function store(Request $request, CaptureLead $capture): JsonResponse
    {
        $tenant = $this->resolveTenant();

        if ($tenant === null) {
            return response()->json(['ok' => true, 'skipped' => 'no_tenant']);
        }

        $captured = 0;

        foreach ((array) $request->input('entry', []) as $entry) {
            foreach ((array) ($entry['changes'] ?? []) as $change) {
                if (($change['field'] ?? null) !== 'leadgen') {
                    continue;
                }

                $value = (array) ($change['value'] ?? []);
                $fields = $this->mapFieldData((array) ($value['field_data'] ?? []));

                if (($fields['name'] ?? '') === '' || ($fields['phone'] ?? '') === '') {
                    continue; // leadgen_id-only payload or incomplete — skip (see class doc).
                }

                $capture->handle($tenant, [
                    'lead_type' => 'masterclass',
                    'name' => $fields['name'],
                    'phone' => $fields['phone'],
                    'email' => $fields['email'] ?? null,
                    'course_slug' => $fields['course_slug'] ?? null,
                    'utm_source' => 'meta_lead_ads',
                    'utm_medium' => 'paid_social',
                    'utm_campaign' => $value['ad_id'] ?? $value['form_id'] ?? null,
                    'page' => 'meta_lead_ads',
                    'consent_version' => 'meta',
                ]);
                $captured++;
            }
        }

        return response()->json(['ok' => true, 'captured' => $captured]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $fieldData
     * @return array<string, string>
     */
    private function mapFieldData(array $fieldData): array
    {
        $out = [];

        foreach ($fieldData as $field) {
            $name = strtolower((string) ($field['name'] ?? ''));
            $value = (string) (($field['values'][0] ?? '') ?: '');

            match (true) {
                in_array($name, ['full_name', 'name', 'first_name'], true) => $out['name'] = $value,
                in_array($name, ['phone_number', 'phone'], true) => $out['phone'] = $value,
                $name === 'email' => $out['email'] = $value,
                in_array($name, ['course', 'course_slug', 'program'], true) => $out['course_slug'] = $value,
                default => null,
            };
        }

        return $out;
    }

    private function resolveTenant(): ?Tenant
    {
        $slug = (string) config('services.meta.tenant_slug');
        if ($slug === '') {
            $slug = (string) config('tenancy.default_slug');
        }

        if ($slug === '') {
            return null;
        }

        return Tenant::query()->where('slug', $slug)->where('status', 'active')->first();
    }
}
