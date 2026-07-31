<?php

declare(strict_types=1);

use App\Models\Product;
use App\Models\ProductPurchase;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Razorpay\FakeRazorpayClient;
use App\Support\Razorpay\RazorpayClient;
use Database\Seeders\RolePermissionSeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    app()->instance(RazorpayClient::class, new FakeRazorpayClient);
    $this->tenant = Tenant::factory()->create();
    $this->admin = User::factory()->for($this->tenant)->create(['user_type' => 'staff']);
    $this->admin->assignRole('admin');
    $this->student = User::factory()->for($this->tenant)->create(['user_type' => 'student']);
    $this->pack = Product::factory()->for($this->tenant)->create(['sku' => 'voice-3pack', 'feature' => 'voice_mock', 'price_paise' => 59_900, 'grant_amount' => 3]);
});

it('student lists the store and starts a purchase', function () {
    Sanctum::actingAs($this->student);

    $this->getJson('/api/v1/me/store')->assertOk()->assertJsonPath('data.products.0.sku', 'voice-3pack');

    // The browser needs the order id AND the publishable key to open Razorpay
    // Checkout — without them the Buy button silently leaves a pending purchase.
    config(['services.razorpay.key_id' => 'rzp_test_visible']);

    $this->postJson('/api/v1/me/purchases', ['product_id' => $this->pack->id])
        ->assertCreated()
        ->assertJsonPath('data.amount_paise', 59_900)
        ->assertJsonPath('data.razorpay_key_id', 'rzp_test_visible')
        ->assertJsonStructure(['data' => ['purchase_id', 'razorpay_order_id', 'amount_paise', 'razorpay_key_id', 'prefill']]);

    expect(ProductPurchase::withoutGlobalScopes()->where('user_id', $this->student->id)->count())->toBe(1);
});

it('student sees only their own purchases', function () {
    ProductPurchase::factory()->for($this->tenant)->create(['user_id' => $this->student->id]);
    $other = User::factory()->for($this->tenant)->create(['user_type' => 'student']);
    ProductPurchase::factory()->for($this->tenant)->create(['user_id' => $other->id]);

    Sanctum::actingAs($this->student);

    $this->getJson('/api/v1/me/purchases')->assertOk()->assertJsonCount(1, 'data');
});

it('admin manages settings, products, revenue and refunds', function () {
    Sanctum::actingAs($this->admin);

    $this->getJson('/api/v1/admin/monetization-settings')->assertOk()->assertJsonPath('data.self_paced_pct_bps', 5000);
    $this->putJson('/api/v1/admin/monetization-settings', ['self_paced_pct_bps' => 4000])
        ->assertOk()->assertJsonPath('data.self_paced_pct_bps', 4000);

    $id = $this->postJson('/api/v1/admin/products', ['sku' => 'cv-3pack', 'name' => 'CV 3-pack', 'feature' => 'cv', 'kind' => 'pack', 'price_paise' => 9_900, 'grant_amount' => 3])
        ->assertCreated()->json('data.id');
    $this->getJson('/api/v1/admin/products')->assertOk()->assertJsonFragment(['sku' => 'cv-3pack']);
    $this->deleteJson("/api/v1/admin/products/{$id}")->assertOk();
    expect(Product::withoutGlobalScopes()->find($id)->active)->toBeFalse();

    $captured = ProductPurchase::factory()->for($this->tenant)->captured()->create(['user_id' => $this->student->id, 'feature' => 'voice_mock', 'amount_paise' => 59_900, 'razorpay_payment_id' => 'pay_X']);
    $this->getJson('/api/v1/admin/revenue')->assertOk()->assertJsonPath('data.gross_paise', 59_900);

    $this->postJson("/api/v1/admin/purchases/{$captured->id}/refund", ['reason' => 'goodwill'])->assertOk();
    expect(ProductPurchase::withoutGlobalScopes()->find($captured->id)->status->value)->toBe('refunded');
});

it('denies a staff user without manage-monetization', function () {
    $mentor = User::factory()->for($this->tenant)->create(['user_type' => 'staff']);
    $mentor->assignRole('mentor');

    Sanctum::actingAs($mentor);

    $this->getJson('/api/v1/admin/products')->assertForbidden();
});

it('404s a foreign tenant product', function () {
    $foreign = Product::factory()->for(Tenant::factory()->create())->create();

    Sanctum::actingAs($this->admin);

    $this->putJson("/api/v1/admin/products/{$foreign->id}", ['price_paise' => 1])->assertNotFound();
});
