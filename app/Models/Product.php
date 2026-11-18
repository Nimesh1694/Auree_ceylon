<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id',
        'name_en',
        'name_si',
        'slug',
        'sku',
        'short_description_en',
        'short_description_si',
        'description_en',
        'description_si',
        'ingredients_en',
        'ingredients_si',
        'directions_en',
        'directions_si',
        'price',
        'compare_at_price',
        'cost_price',
        'stock_quantity',
        'low_stock_threshold',
        'expiry_date',
        'is_active',
        'is_featured',
        'is_best_seller',
        'is_new_arrival',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'compare_at_price' => 'decimal:2',
            'cost_price' => 'decimal:2',
            'rating_cache' => 'decimal:2',
            'reviews_count' => 'integer',
            'stock_quantity' => 'integer',
            'low_stock_threshold' => 'integer',
            'expiry_date' => 'date',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
            'is_best_seller' => 'boolean',
            'is_new_arrival' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order');
    }

    public function primaryImage(): HasOne
    {
        return $this->hasOne(ProductImage::class)->where('is_primary', true);
    }

    public function inventoryMovements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class)->latest();
    }

    public function productSuppliers(): HasMany
    {
        return $this->hasMany(ProductSupplier::class);
    }

    public function suppliers(): BelongsToMany
    {
        return $this->belongsToMany(Supplier::class, 'product_supplier')
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

    public function preferredSupplier(): HasOne
    {
        return $this->hasOne(ProductSupplier::class)->where('is_preferred', true);
    }

    public function purchaseOrderItems(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function stockAlerts(): HasMany
    {
        return $this->hasMany(StockAlert::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(ProductReview::class);
    }

    public function approvedReviews(): HasMany
    {
        return $this->hasMany(ProductReview::class)->where('status', 'approved');
    }

    public function localizedName(string $locale = null): string
    {
        $locale = $locale ?? app()->getLocale();
        return ($locale === 'si' && !empty($this->name_si)) ? $this->name_si : $this->name_en;
    }

    public function localizedDescription(string $locale = null): string
    {
        $locale = $locale ?? app()->getLocale();
        return ($locale === 'si' && !empty($this->description_si)) ? $this->description_si : ($this->description_en ?? '');
    }

    public function isInStock(): bool
    {
        return $this->stock_quantity > 0;
    }

    public function recentlyViewedBy(): HasMany
    {
        return $this->hasMany(RecentlyViewedProduct::class, 'product_id');
    }
}

