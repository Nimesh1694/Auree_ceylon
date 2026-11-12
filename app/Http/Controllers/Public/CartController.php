<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Services\CartService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CartController extends Controller
{
    /**
     * Display the shopping cart.
     */
    public function index(CartService $cartService): Response
    {
        $cart = $cartService->getDetails();

        return Inertia::render('Cart/Index', [
            'cart' => $cart,
        ]);
    }

    /**
     * Add an item to the shopping cart.
     */
    public function add(Request $request, CartService $cartService): RedirectResponse
    {
        $validated = $request->validate([
            'product_id' => 'required|integer|exists:products,id',
            'quantity' => 'nullable|integer|min:1|max:99',
        ]);

        $quantity = $validated['quantity'] ?? 1;
        $cartService->add($validated['product_id'], $quantity);

        return back()->with('success', 'Product added to your cart.');
    }

    /**
     * Update quantity of an item in the cart.
     */
    public function update(Request $request, CartService $cartService): RedirectResponse
    {
        $validated = $request->validate([
            'product_id' => 'required|integer|exists:products,id',
            'quantity' => 'required|integer|min:0|max:99',
        ]);

        $cartService->update($validated['product_id'], $validated['quantity']);

        return back()->with('success', 'Cart updated.');
    }

    /**
     * Remove an item from the cart.
     */
    public function remove(int $productId, CartService $cartService): RedirectResponse
    {
        $cartService->remove($productId);

        return back()->with('success', 'Item removed from your cart.');
    }

    /**
     * Clear all items from the cart.
     */
    public function clear(CartService $cartService): RedirectResponse
    {
        $cartService->clear();

        return back()->with('success', 'Your cart is now empty.');
    }
}

