<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Services\StockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InventoryController extends Controller
{
    /**
     * Display inventory overview and low-stock items.
     */
    public function index(Request $request): Response
    {
        $query = Product::with('category');

        if ($request->filled('search')) {
            $search = trim($request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('name_en', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%");
            });
        }

        if ($request->boolean('low_stock_only', false)) {
            $query->whereColumn('stock_quantity', '<=', 'low_stock_threshold');
        }

        $products = $query->orderBy('stock_quantity')->paginate(20)->withQueryString();

        $lowStockCount = Product::whereColumn('stock_quantity', '<=', 'low_stock_threshold')->count();
        $outOfStockCount = Product::where('stock_quantity', '<=', 0)->count();
        $totalItemsCount = Product::sum('stock_quantity');

        return Inertia::render('Admin/Inventory/Index', [
            'products' => $products,
            'filters' => $request->only(['search', 'low_stock_only']),
            'stats' => [
                'low_stock_count' => $lowStockCount,
                'out_of_stock_count' => $outOfStockCount,
                'total_stock_count' => $totalItemsCount,
            ],
        ]);
    }

    /**
     * Display immutable historical movement ledger.
     */
    public function movements(Request $request): Response
    {
        $query = InventoryMovement::with(['product', 'order', 'user']);

        if ($request->filled('product_id')) {
            $query->where('product_id', $request->input('product_id'));
        }

        if ($request->filled('type')) {
            $query->where('movement_type', $request->input('type'));
        }

        $movements = $query->latest('id')->paginate(25)->withQueryString();
        $products = Product::orderBy('name_en')->get(['id', 'name_en', 'sku']);

        return Inertia::render('Admin/Inventory/Movements', [
            'movements' => $movements,
            'products' => $products,
            'filters' => $request->only(['product_id', 'type']),
        ]);
    }

    /**
     * Perform an atomic manual stock adjustment.
     */
    public function adjust(Request $request, StockService $stockService): RedirectResponse
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity_delta' => 'required|integer',
            'movement_type' => 'required|string|in:restock,adjustment,damage_loss,expired',
            'reason' => 'required|string|min:3|max:255',
        ]);

        $product = Product::findOrFail($validated['product_id']);

        $stockService->adjustStock(
            product: $product,
            quantityDelta: (int) $validated['quantity_delta'],
            movementType: $validated['movement_type'],
            reason: $validated['reason'],
            user: $request->user()
        );

        return back()->with('success', "Stock updated for {$product->name_en}.");
    }
}

