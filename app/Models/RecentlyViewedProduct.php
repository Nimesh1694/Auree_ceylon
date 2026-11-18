<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecentlyViewedProduct extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $table = 'recently_viewed_products';

    protected $fillable = [
        'customer_id',
        'product_id',
        'viewed_at',
    ];

    protected $casts = [
        'customer_id' => 'integer',
        'product_id' => 'integer',
        'viewed_at' => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}

