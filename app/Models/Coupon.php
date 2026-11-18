<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class Coupon extends Model
{
    use HasFactory;

    public const TYPE_PERCENTAGE = 'percentage';
    public const TYPE_FIXED_AMOUNT = 'fixed_amount';
    public const TYPE_FREE_SHIPPING = 'free_shipping';

    public const APPLIES_ALL = 'all';
    public const APPLIES_CATEGORIES = 'specific_categories';
    public const APPLIES_PRODUCTS = 'specific_products';

    public const DEFAULT_PERCENTAGE_CAP_CENTS = 500000; // LKR 5,000.00 ceiling in integer cents

    protected $table = 'coupons';

    protected $fillable = [
        'code',
        'name',
        'type',
        'value',
        'min_order_subtotal',
        'max_discount_amount',
        'is_unlimited_cap',
        'usage_limit_total',
        'usage_limit_per_customer',
        'times_used',
        'is_active',
        'is_public',
        'applies_to',
        'allow_agent_referral',
        'starts_at',
        'expires_at',
        'created_by',
    ];

    protected $casts = [
        'value' => 'decimal:2',
        'min_order_subtotal' => 'decimal:2',
        'max_discount_amount' => 'decimal:2',
        'is_unlimited_cap' => 'boolean',
        'is_active' => 'boolean',
        'is_public' => 'boolean',
        'allow_agent_referral' => 'boolean',
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
        'usage_limit_total' => 'integer',
        'usage_limit_per_customer' => 'integer',
        'times_used' => 'integer',
    ];

    /**
     * Categories restricted to this coupon.
     */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(
            Category::class,
            'coupon_categories',
            'coupon_id',
            'category_id'
        );
    }

    /**
     * Products restricted to this coupon.
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(
            Product::class,
            'coupon_products',
            'coupon_id',
            'product_id'
        );
    }

    /**
     * Historical usage records for this coupon.
     */
    public function usages(): HasMany
    {
        return $this->hasMany(CouponUsage::class, 'coupon_id');
    }

    /**
     * Orders that used this coupon.
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'coupon_id');
    }

    /**
     * Staff member or Owner who created this coupon.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Determine if the coupon is currently valid based on active status and validity window.
     */
    public function isValidNow(): bool
    {
        if (!$this->is_active) {
            return false;
        }

        $now = Carbon::now();

        if ($this->starts_at && $now->lt($this->starts_at)) {
            return false;
        }

        if ($this->expires_at && $now->gt($this->expires_at)) {
            return false;
        }

        return true;
    }

    public function isPercentage(): bool
    {
        return $this->type === self::TYPE_PERCENTAGE;
    }

    public function isFixedAmount(): bool
    {
        return $this->type === self::TYPE_FIXED_AMOUNT;
    }

    public function isFreeShipping(): bool
    {
        return $this->type === self::TYPE_FREE_SHIPPING;
    }

    /**
     * Return effective maximum discount cap in integer cents.
     * Returns null if unlimited cap is authorized.
     */
    public function getEffectiveCapCents(): ?int
    {
        if ($this->is_unlimited_cap) {
            return null; // Unlimited cap
        }

        if ($this->max_discount_amount !== null && bccomp((string)$this->max_discount_amount, '0.00', 2) > 0) {
            return intval(bcmul((string)$this->max_discount_amount, '100', 0));
        }

        return self::DEFAULT_PERCENTAGE_CAP_CENTS;
    }
}

