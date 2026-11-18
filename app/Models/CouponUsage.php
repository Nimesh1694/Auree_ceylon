<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CouponUsage extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $table = 'coupon_usages';

    protected $fillable = [
        'coupon_id',
        'order_id',
        'customer_id',
        'customer_email',
        'customer_phone',
        'discount_amount',
        'shipping_discount_amount',
        'created_at',
        'restored_at',
    ];

    protected $casts = [
        'discount_amount' => 'decimal:2',
        'shipping_discount_amount' => 'decimal:2',
        'created_at' => 'datetime',
        'restored_at' => 'datetime',
    ];

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class, 'coupon_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }
}

