<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supplier extends Model
{
    use HasFactory;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';

    public const TERMS_COD = 'cod';
    public const TERMS_ADVANCE = 'advance';
    public const TERMS_NET_7 = 'net_7';
    public const TERMS_NET_15 = 'net_15';
    public const TERMS_NET_30 = 'net_30';
    public const TERMS_NET_60 = 'net_60';

    protected $fillable = [
        'code',
        'name',
        'contact_person',
        'email',
        'phone',
        'address',
        'city',
        'country',
        'tax_id',
        'payment_terms',
        'default_lead_time_days',
        'status',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'default_lead_time_days' => 'integer',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function productSuppliers(): HasMany
    {
        return $this->hasMany(ProductSupplier::class);
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_supplier')
            ->using(ProductSupplier::class)
            ->withPivot([
                'id',
                'supplier_sku',
                'unit_cost',
                'lead_time_days',
                'min_order_qty',
                'is_preferred',
                'last_purchased_at',
            ])
            ->withTimestamps();
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }
}

