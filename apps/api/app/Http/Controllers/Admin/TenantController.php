<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Support\Audit\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Super-admin tenant management (PRD §6.24 whitelabel / launch prep). Provision
 * new tenants, edit their whitelabel branding (colors, fonts, logo, domain), and
 * toggle feature flags. Gated by can:manage-settings — only a super-admin
 * satisfies it. The Tenant model is the ownership root and is never tenant-scoped,
 * so a super-admin edits any tenant deliberately.
 */
final class TenantController extends Controller
{
    /** The default whitelabel palette a new tenant starts from (BrowseJobs tokens). */
    private const DEFAULT_BRANDING = [
        'colors' => [
            'ink_navy' => '#0A1220', 'deep_navy' => '#0E3FA9', 'trust_blue' => '#1B6DF0',
            'sky' => '#E7F1FE', 'verify_green' => '#0BA860', 'warn_red' => '#D64545',
            'amber' => '#F5A623', 'paper' => '#F6F9FE', 'line' => '#DCE6F5', 'muted' => '#5A6B85',
        ],
        'fonts' => ['display' => 'Sora', 'body' => 'Inter', 'mono' => 'IBM Plex Mono'],
        'logo_url' => null,
    ];

    public function __construct(private readonly AuditLogger $audit) {}

    public function index(): JsonResponse
    {
        $tenants = Tenant::query()
            ->withCount('users')
            ->orderBy('id')
            ->get()
            ->map(fn (Tenant $t) => [
                'id' => $t->id,
                'name' => $t->name,
                'slug' => $t->slug,
                'domain' => $t->domain,
                'plan' => $t->plan,
                'status' => $t->status,
                'users_count' => $t->users_count,
                'branding' => $t->branding,
                'feature_flags' => $t->feature_flags ?? [],
            ]);

        return response()->json(['data' => $tenants]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['nullable', 'string', 'max:60', 'alpha_dash', 'unique:tenants,slug'],
            'domain' => ['nullable', 'string', 'max:190', 'unique:tenants,domain'],
            'plan' => ['nullable', 'string', 'max:40'],
        ]);

        $slug = $data['slug'] ?? Str::slug($data['name']);
        abort_if(Tenant::query()->where('slug', $slug)->exists(), 422, 'A tenant with that slug already exists.');

        $tenant = Tenant::query()->create([
            'name' => $data['name'],
            'slug' => $slug,
            'domain' => $data['domain'] ?? null,
            'plan' => $data['plan'] ?? 'standard',
            'status' => 'active',
            'batch_numbering_pattern' => '{COURSE}-{YYYYMM}-{seq}',
            'branding' => self::DEFAULT_BRANDING,
            'feature_flags' => [],
        ]);

        $this->audit->log('tenant.provisioned', null, ['tenant_id' => $tenant->id, 'slug' => $slug], $request->user());

        return response()->json(['data' => ['id' => $tenant->id, 'slug' => $tenant->slug]], 201);
    }

    public function updateBranding(Request $request, Tenant $tenant): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:120'],
            'domain' => ['nullable', 'string', 'max:190', Rule::unique('tenants', 'domain')->ignore($tenant->id)],
            'status' => ['sometimes', Rule::in(['active', 'suspended'])],
            'branding' => ['sometimes', 'array'],
            'branding.colors' => ['sometimes', 'array'],
            'branding.colors.*' => ['string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'branding.fonts' => ['sometimes', 'array'],
            'branding.fonts.*' => ['string', 'max:60'],
            'branding.logo_url' => ['sometimes', 'nullable', 'url', 'max:500'],
        ]);

        $update = array_filter([
            'name' => $data['name'] ?? null,
            'status' => $data['status'] ?? null,
        ], fn ($v) => $v !== null);

        if (array_key_exists('domain', $data)) {
            $update['domain'] = $data['domain'];
        }
        if (isset($data['branding'])) {
            // Merge so a partial update (e.g. just the logo) keeps the rest.
            $update['branding'] = array_replace_recursive($tenant->branding ?? self::DEFAULT_BRANDING, $data['branding']);
        }

        $tenant->update($update);
        $this->audit->log('tenant.branding_updated', null, ['tenant_id' => $tenant->id], $request->user());

        return response()->json(['data' => ['branding' => $tenant->branding, 'domain' => $tenant->domain, 'name' => $tenant->name]]);
    }

    public function updateFlags(Request $request, Tenant $tenant): JsonResponse
    {
        $data = $request->validate([
            'feature_flags' => ['required', 'array'],
            'feature_flags.*' => ['boolean'],
        ]);

        $tenant->update(['feature_flags' => array_merge($tenant->feature_flags ?? [], $data['feature_flags'])]);
        $this->audit->log('tenant.flags_updated', null, ['tenant_id' => $tenant->id, 'flags' => array_keys($data['feature_flags'])], $request->user());

        return response()->json(['data' => ['feature_flags' => $tenant->feature_flags]]);
    }
}
