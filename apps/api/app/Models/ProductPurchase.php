<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ProductKind;
use App\Enums\PurchaseStatus;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * An in-app purchase (PRD §6.17). Settled by the Razorpay webhook; carries its own
 * inclusive-GST breakup + receipt. Money in paise.
 *
 * @property int $id
 * @property int|null $tenant_id
 * @property int $user_id
 * @property int|null $product_id
 * @property int|null $subscription_id
 * @property string $sku
 * @property string|null $feature
 * @property ProductKind $kind
 * @property int $amount_paise
 * @property int $total_paise
 * @property PurchaseStatus $status
 * @property string|null $razorpay_order_id
 * @property string|null $receipt_number
 * @property string|null $receipt_path
 * @property Carbon|null $captured_at
 * @property Carbon|null $refunded_at
 * @property-read Product|null $product
 */
class ProductPurchase extends Model
{
    use BelongsToTenant;
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'tenant_id', 'user_id', 'product_id', 'subscription_id', 'sku', 'feature', 'kind',
        'amount_paise', 'taxable_paise', 'cgst_paise', 'sgst_paise', 'total_paise', 'status',
        'razorpay_order_id', 'razorpay_payment_id', 'receipt_number', 'receipt_path',
        'captured_at', 'refunded_at', 'refund_reason', 'meta',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => ProductKind::class,
            'status' => PurchaseStatus::class,
            'amount_paise' => 'integer',
            'taxable_paise' => 'integer',
            'cgst_paise' => 'integer',
            'sgst_paise' => 'integer',
            'total_paise' => 'integer',
            'captured_at' => 'datetime',
            'refunded_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
