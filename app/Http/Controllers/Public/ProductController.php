<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    /**
     * Display a listing of database-backed products with filtering, searching, and sorting.
     */
    public function index(Request $request): Response
    {
        $query = Product::with(['category', 'primaryImage'])
            ->where('is_active', true);

        // Category filter
        if ($request->filled('category')) {
            $categorySlug = $request->input('category');
            $query->whereHas('category', function ($q) use ($categorySlug) {
                $q->where('slug', $categorySlug);
            });
        }

        // Search filter (English & Sinhala name, SKU, descriptions)
        if ($request->filled('search')) {
            $search = trim($request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('name_en', 'like', "%{$search}%")
                    ->orWhere('name_si', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%")
                    ->orWhere('short_description_en', 'like', "%{$search}%")
                    ->orWhere('short_description_si', 'like', "%{$search}%");
            });
        }

        // Badge filter (featured, best seller, new arrival)
        if ($request->filled('filter')) {
            $filter = $request->input('filter');
            match ($filter) {
                'featured' => $query->where('is_featured', true),
                'bestseller' => $query->where('is_best_seller', true),
                'new_arrival' => $query->where('is_new_arrival', true),
                default => null,
            };
        }

        // Price range filtering
        if ($request->filled('min_price')) {
            $query->where('price', '>=', (float) $request->input('min_price'));
        }
        if ($request->filled('max_price')) {
            $query->where('price', '<=', (float) $request->input('max_price'));
        }

        // In-stock filter
        if ($request->boolean('in_stock')) {
            $query->where('stock_quantity', '>', 0);
        }

        // Sorting
        $sort = $request->input('sort', 'newest');
        match ($sort) {
            'price_low_high' => $query->orderBy('price', 'asc'),
            'price_high_low' => $query->orderBy('price', 'desc'),
            'name_asc' => $query->orderBy('name_en', 'asc'),
            default => $query->latest('id'),
        };

        $products = $query->paginate(12)->withQueryString();

        $categories = Category::where('is_active', true)
            ->withCount(['products' => fn ($q) => $q->where('is_active', true)])
            ->orderBy('sort_order')
            ->get();

        return Inertia::render('Products/Index', [
            'products' => $products,
            'categories' => $categories,
            'filters' => (object) $request->only(['category', 'search', 'filter', 'min_price', 'max_price', 'in_stock', 'sort']),
        ]);
    }

    /**
     * Display a specific product details page.
     */
    public function show(string $slug): Response
    {
        $product = Product::with(['category', 'images'])
            ->where('is_active', true)
            ->where('slug', $slug)
            ->firstOrFail();

        // Related products in the same category
        $relatedProducts = Product::with(['category', 'primaryImage'])
            ->where('is_active', true)
            ->where('id', '!=', $product->id)
            ->when($product->category_id, fn ($q) => $q->where('category_id', $product->category_id))
            ->take(4)
            ->get();

        return Inertia::render('Products/Show', [
            'product' => $product,
            'relatedProducts' => $relatedProducts,
        ]);
    }
}

