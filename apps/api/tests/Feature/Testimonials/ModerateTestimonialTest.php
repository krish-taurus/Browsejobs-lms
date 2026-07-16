<?php

declare(strict_types=1);

use App\Enums\TestimonialStatus;
use App\Models\AuditLog;
use App\Models\Review;
use App\Models\Tenant;
use App\Models\Testimonial;
use App\Models\User;
use App\Models\Voucher;
use App\Models\VoucherIssue;
use Database\Seeders\RolePermissionSeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->tenant = Tenant::factory()->domain('rev.test')->create();
    $this->admin = User::factory()->for($this->tenant)->create(['user_type' => 'staff']);
    $this->admin->assignRole('admin');
    $this->student = User::factory()->for($this->tenant)->create(['user_type' => 'student', 'name' => 'Asha K']);
    withinTenant($this->tenant, fn () => Voucher::factory()->for($this->tenant)->reviewReward()->create());
});

it('approves a testimonial: publishes a review and issues a voucher', function () {
    $testimonial = Testimonial::factory()->for($this->tenant)->create([
        'user_id' => $this->student->id, 'body' => 'Real interviews, real prep.', 'course_slug' => 'python-fullstack',
    ]);

    Sanctum::actingAs($this->admin);

    $this->postJson("/api/v1/admin/testimonials/{$testimonial->id}/approve")
        ->assertOk()
        ->assertJsonPath('data.status', 'approved');

    expect($testimonial->fresh()->status)->toBe(TestimonialStatus::Approved)
        ->and(Review::withoutGlobalScopes()->where('source', 'platform')->where('is_active', true)->where('author_name', 'Asha K')->exists())->toBeTrue()
        ->and(VoucherIssue::withoutGlobalScopes()->where('user_id', $this->student->id)->count())->toBe(1)
        ->and(AuditLog::withoutGlobalScopes()->where('action', 'testimonial.approved')->count())->toBe(1);

    // The published testimonial now shows on the public /reviews wall.
    $this->getJson('http://rev.test/api/v1/reviews')
        ->assertOk()
        ->assertJsonFragment(['body' => 'Real interviews, real prep.']);
});

it('rejects a testimonial: no review, no voucher', function () {
    $testimonial = Testimonial::factory()->for($this->tenant)->create(['user_id' => $this->student->id]);

    Sanctum::actingAs($this->admin);

    $this->postJson("/api/v1/admin/testimonials/{$testimonial->id}/reject", ['reason' => 'Off-topic.'])
        ->assertOk()
        ->assertJsonPath('data.status', 'rejected');

    expect(Review::withoutGlobalScopes()->count())->toBe(0)
        ->and(VoucherIssue::withoutGlobalScopes()->count())->toBe(0)
        ->and(AuditLog::withoutGlobalScopes()->where('action', 'testimonial.rejected')->count())->toBe(1);
});

it('denies a staff user without manage-vouchers', function () {
    $mentor = User::factory()->for($this->tenant)->create(['user_type' => 'staff']);
    $mentor->assignRole('mentor');
    $testimonial = Testimonial::factory()->for($this->tenant)->create(['user_id' => $this->student->id]);

    Sanctum::actingAs($mentor);

    $this->postJson("/api/v1/admin/testimonials/{$testimonial->id}/approve")->assertForbidden();
});

it('404s a foreign tenant testimonial', function () {
    $foreign = Testimonial::factory()->for(Tenant::factory()->create())->create();

    Sanctum::actingAs($this->admin);

    $this->postJson("/api/v1/admin/testimonials/{$foreign->id}/approve")->assertNotFound();
});
