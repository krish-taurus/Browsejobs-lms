<?php

declare(strict_types=1);

use App\Enums\EnrolmentType;
use App\Models\CreditTransaction;
use App\Models\Entitlement;
use App\Models\QuotaUsage;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Entitlements\EntitlementService;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->student = User::factory()->for($this->tenant)->create(['user_type' => 'student']);
    $this->svc = app(EntitlementService::class);
});

it('grants, consumes and reports credit balance with a ledger', function () {
    withinTenant($this->tenant, function () {
        $this->svc->grantCredits($this->student, 'voice_mock', 3, 'purchase');
        expect($this->svc->balance($this->student, 'voice_mock'))->toBe(3);

        $this->svc->consumeCredits($this->student, 'voice_mock', 1, 'used');
        expect($this->svc->balance($this->student, 'voice_mock'))->toBe(2)
            ->and(CreditTransaction::withoutGlobalScopes()->where('user_id', $this->student->id)->count())->toBe(2)
            ->and(QuotaUsage::withoutGlobalScopes()->where('user_id', $this->student->id)->where('feature', 'voice_mock')->value('used'))->toBe(1);
    });
});

it('refuses to consume more than the balance', function () {
    expect(fn () => withinTenant($this->tenant, fn () => $this->svc->consumeCredits($this->student, 'voice_mock', 1, 'used')))
        ->toThrow(ValidationException::class);
});

it('grants the included quota by enrolment type', function () {
    withinTenant($this->tenant, function () {
        $this->svc->grantIncludedQuota($this->student, EnrolmentType::SelfPaced);
        expect($this->svc->balance($this->student, 'voice_mock'))->toBe(2); // self-paced default
    });
});

it('grants, checks and revokes an access entitlement', function () {
    withinTenant($this->tenant, function () {
        $this->svc->grantEntitlement($this->student, 'self_paced:9', 'self_paced');
        expect($this->svc->hasEntitlement($this->student, 'self_paced:9'))->toBeTrue();

        $this->svc->revokeEntitlement($this->student, 'self_paced:9');
        expect($this->svc->hasEntitlement($this->student, 'self_paced:9'))->toBeFalse()
            ->and(Entitlement::withoutGlobalScopes()->where('key', 'self_paced:9')->first()->status->value)->toBe('revoked');
    });
});

it('treats an expired entitlement as inactive', function () {
    withinTenant($this->tenant, function () {
        $this->svc->grantEntitlement($this->student, 'career_plus', 'career_plus', now()->subDay());
        expect($this->svc->hasEntitlement($this->student, 'career_plus'))->toBeFalse();
    });
});

it('claws back only what remains', function () {
    withinTenant($this->tenant, function () {
        $this->svc->grantCredits($this->student, 'cv', 3, 'purchase');
        $this->svc->consumeCredits($this->student, 'cv', 2, 'used');
        $this->svc->clawbackCredits($this->student, 'cv', 3, 'refund'); // only 1 left
        expect($this->svc->balance($this->student, 'cv'))->toBe(0);
    });
});
