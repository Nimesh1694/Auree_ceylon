<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Setting;
use Illuminate\Support\Facades\Session;

class CartService
{
    private const SESSION_KEY = 'cart';

    /**
     * Get raw cart contents from session [product_id => quantity].
     */
    public function getRawCart(): array
    {
        return Session::get(self::SESSION_KEY, []);
    }

    /**
     * Add product to cart with stock validation.
     */
    public function add(int $productId, int $quantity = 1): array
    {
        $product = Product::where('is_active', true)->findOrFail($productId);
        $cart = $this->getRawCart();

        $currentQty = $cart[$productId] ?? 0;
        $newQty = max(1, $currentQty + $quantity);

        // Cap at available stock
        if ($newQty > $product->stock_quantity) {
            $newQty = $product->stock_quantity;
        }

        if ($newQty > 0) {
            $cart[$productId] = $newQty;
        }

        Session::put(self::SESSION_KEY, $cart);

        return $this->getDetails();
    }

    /**
     * Update quantity for a specific product in the cart.
     */
    public function update(int $productId, int $quantity): array
    {
        $cart = $this->getRawCart();

        if ($quantity <= 0) {
            unset($cart[$productId]);
        } else {
            $product = Product::where('is_active', true)->find($productId);
            if ($product) {
                $cart[$productId] = min($quantity, $product->stock_quantity);
            } else {
                unset($cart[$productId]);
            }
        }

        Session::put(self::SESSION_KEY, $cart);

        return $this->getDetails();
    }

    /**
     * Remove an item from the cart.
     */
    public function remove(int $productId): array
    {
        $cart = $this->getRawCart();
        unset($cart[$productId]);
        Session::put(self::SESSION_KEY, $cart);

        return $this->getDetails();
    }

    /**
     * Clear the cart completely.
     */
    public function clear(): void
    {
        Session::forget(self::SESSION_KEY);
    }

    /**
     * Compute full verified cart details backed by database products and server-side pricing.
     * Never trusts prices sent by the browser.
     */
    public function getDetails(): array
    {
        $rawCart = $this->getRawCart();
        $productIds = array_keys($rawCart);

        if (empty($productIds)) {
            return [
                'items' => [],
                'item_count' => 0,
                'subtotal' => 0.00,
                'delivery_charge' => 0.00,
                'total' => 0.00,
                'has_out_of_stock' => false,
            ];
        }

        $products = Product::with('category', 'primaryImage')
            ->whereIn('id', $productIds)
            ->get()
            ->keyBy('id');

        $items = [];
        $subtotal = 0.00;
        $hasOutOfStock = false;

        foreach ($rawCart as $productId => $quantity) {
            $product = $products->get($productId);

            if (!$product || !$product->is_active) {
                // Inactive or deleted product: mark as out of stock and ignore from subtotal
                $hasOutOfStock = true;
                continue;
            }

            $availableStock = $product->stock_quantity;
            $isOverStock = $quantity > $availableStock;
            if ($isOverStock || $availableStock <= 0) {
                $hasOutOfStock = true;
            }

            $unitPrice = (float) $product->price;
            $lineTotal = round($unitPrice * $quantity, 2);
            $subtotal += $lineTotal;

            $items[] = [
                'product_id' => $product->id,
                'name_en' => $product->name_en,
                'name_si' => $product->name_si,
                'slug' => $product->slug,
                'sku' => $product->sku,
                'unit_price' => $unitPrice,
                'compare_at_price' => $product->compare_at_price ? (float) $product->compare_at_price : null,
                'quantity' => $quantity,
                'available_stock' => $availableStock,
                'is_over_stock' => $isOverStock,
                'subtotal' => $lineTotal,
                'image' => $product->primaryImage?->path,
                'category_name_en' => $product->category?->name_en,
                'category_name_si' => $product->category?->name_si,
            ];
        }

        $subtotal = round($subtotal, 2);
        $deliveryCharge = $subtotal > 0
            ? (float) Setting::get('default_delivery_charge', 350.00)
            : 0.00;

        $total = round($subtotal + $deliveryCharge, 2);

        return [
            'items' => $items,
            'item_count' => array_sum(array_column($items, 'quantity')),
            'subtotal' => $subtotal,
            'delivery_charge' => $deliveryCharge,
            'total' => $total,
            'has_out_of_stock' => $hasOutOfStock,
        ];
    }
}

