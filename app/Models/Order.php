<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_number',
        'customer_id',
        'customer_name',
        'customer_phone',
        'customer_email',
        'shipping_address',
        'district',
        'city',
        'postal_code',
        'subtotal',
        'delivery_charge',
        'total_amount',
        'payment_method',
        'status',
        'agent_id',
        'referral_code',
        'tracking_token',
        'notes',
        'placed_at',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'delivery_charge' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'placed_at' => 'datetime',
        ];
    }

    /**
     * Bootstrap the model with financial and audit deletion protection.
     */
    protected static function booted(): void
    {
        static::deleting(function (Order $order) {
            // Strict Financial & Audit Protection (SEC-05-02)
            if ($order->commissions()->exists()) {
                throw new \Illuminate\Auth\Access\AuthorizationException(
                    "Integrity Violation: Order #{$order->order_number} has associated commission ledger records and cannot be deleted."
                );
            }

            if ($order->codCollection()->where('collected_amount', '>', 0)->exists()) {
                throw new \Illuminate\Auth\Access\AuthorizationException(
                    "Integrity Violation: Order #{$order->order_number} has recorded COD collections and cannot be deleted."
                );
            }

            if ($order->inventoryMovements()->exists()) {
                throw new \Illuminate\Auth\Access\AuthorizationException(
                    "Integrity Violation: Order #{$order->order_number} has associated inventory movements and cannot be deleted."
                );
            }
        });
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function delivery(): HasOne
    {
        return $this->hasOne(Delivery::class);
    }

    public function codCollection(): HasOne
    {
        return $this->hasOne(CodCollection::class);
    }

    public function inventoryMovements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }

    public function commissions(): HasMany
    {
        return $this->hasMany(CommissionLedgerEntry::class);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(OrderNotification::class);
    }

    public static function generateOrderNumber(): string
    {
        $date = now()->format('Ymd');
        $random = strtoupper(Str::random(5));
        return "ORD-{$date}-{$random}";
    }

    public static function generateTrackingToken(): string
    {
        return Str::random(32);
    }
}

