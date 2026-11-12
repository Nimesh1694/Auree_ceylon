<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    /**
     * Display a listing of products with role-based cost price masking.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $canViewCostPrice = in_array($user?->role?->value ?? (string) $user?->role, [
            'owner', 'it_manager', 'accountant',
        ], true);

        $query = Product::with(['category', 'primaryImage']);

        if ($request->filled('search')) {
            $search = trim($request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('name_en', 'like', "%{$search}%")
                    ->orWhere('name_si', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%");
            });
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->input('category_id'));
        }

        if ($request->boolean('low_stock')) {
            $query->whereColumn('stock_quantity', '<=', 'low_stock_threshold');
        }

        $products = $query->latest('id')->paginate(15)->withQueryString();

        // Mask cost_price for unauthorized roles (e.g., marketing_manager)
        if (!$canViewCostPrice) {
            $products->getCollection()->transform(function ($product) {
                $product->cost_price = null;
                return $product;
            });
        }

        $categories = Category::orderBy('name_en')->get(['id', 'name_en', 'name_si']);

        return Inertia::render('Admin/Products/Index', [
            'products' => $products,
            'categories' => $categories,
            'filters' => $request->only(['search', 'category_id', 'low_stock']),
            'canViewCostPrice' => $canViewCostPrice,
        ]);
    }

    /**
     * Show the form for creating a new product.
     */
    public function create(): Response
    {
        $categories = Category::where('is_active', true)->orderBy('name_en')->get();

        return Inertia::render('Admin/Products/Create', [
            'categories' => $categories,
        ]);
    }

    /**
     * Store a newly created product.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'category_id' => 'nullable|exists:categories,id',
            'name_en' => 'required|string|max:255',
            'name_si' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:products,slug',
            'sku' => 'nullable|string|max:50|unique:products,sku',
            'short_description_en' => 'nullable|string',
            'short_description_si' => 'nullable|string',
            'description_en' => 'nullable|string',
            'description_si' => 'nullable|string',
            'ingredients_en' => 'nullable|string',
            'ingredients_si' => 'nullable|string',
            'directions_en' => 'nullable|string',
            'directions_si' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'compare_at_price' => 'nullable|numeric|min:0',
            'cost_price' => 'nullable|numeric|min:0',
            'stock_quantity' => 'required|integer|min:0',
            'low_stock_threshold' => 'required|integer|min:0',
            'expiry_date' => 'nullable|date',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
            'is_best_seller' => 'boolean',
            'is_new_arrival' => 'boolean',
            'images.*' => 'nullable|image|mimes:jpeg,png,webp,jpg|max:2048',
        ]);

        if (empty($validated['slug'])) {
            $validated['slug'] = Str::slug($validated['name_en']);
        }

        $initialStock = (int) $validated['stock_quantity'];

        $product = Product::create($validated);

        // Record opening stock if initial stock > 0
        if ($initialStock > 0) {
            InventoryMovement::create([
                'product_id' => $product->id,
                'movement_type' => InventoryMovement::TYPE_OPENING_STOCK,
                'quantity' => $initialStock,
                'previous_stock' => 0,
                'new_stock' => $initialStock,
                'reason' => 'Initial product catalog creation',
                'user_id' => $request->user()?->id,
            ]);
        }

        // Handle uploaded images
        if ($request->hasFile('images')) {
            $isFirst = true;
            foreach ($request->file('images') as $index => $file) {
                $path = $file->store('products', 'public');
                ProductImage::create([
                    'product_id' => $product->id,
                    'path' => $path,
                    'alt_text' => $product->name_en,
                    'sort_order' => $index,
                    'is_primary' => $isFirst,
                ]);
                $isFirst = false;
            }
        }

        AuditLog::log('product_created', $product, null, [
            'name_en' => $product->name_en,
            'sku' => $product->sku,
            'price' => $product->price,
            'stock' => $product->stock_quantity,
        ]);

        return redirect()->route('admin.products.index')->with('success', 'Product created successfully.');
    }

    /**
     * Show the form for editing the specified product.
     */
    public function edit(Product $product, Request $request): Response
    {
        $user = $request->user();
        $canViewCostPrice = in_array($user?->role?->value ?? (string) $user?->role, [
            'owner', 'it_manager', 'accountant',
        ], true);

        if (!$canViewCostPrice) {
            $product->cost_price = null;
        }

        $categories = Category::where('is_active', true)->orderBy('name_en')->get();
        $product->load(['images']);

        return Inertia::render('Admin/Products/Edit', [
            'product' => $product,
            'categories' => $categories,
            'canViewCostPrice' => $canViewCostPrice,
        ]);
    }

    /**
     * Update the specified product.
     */
    public function update(Request $request, Product $product): RedirectResponse
    {
        $user = $request->user();
        $canEditCostPrice = in_array($user?->role?->value ?? (string) $user?->role, [
            'owner', 'it_manager', 'accountant',
        ], true);

        $validated = $request->validate([
            'category_id' => 'nullable|exists:categories,id',
            'name_en' => 'required|string|max:255',
            'name_si' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:products,slug,' . $product->id,
            'sku' => 'nullable|string|max:50|unique:products,sku,' . $product->id,
            'short_description_en' => 'nullable|string',
            'short_description_si' => 'nullable|string',
            'description_en' => 'nullable|string',
            'description_si' => 'nullable|string',
            'ingredients_en' => 'nullable|string',
            'ingredients_si' => 'nullable|string',
            'directions_en' => 'nullable|string',
            'directions_si' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'compare_at_price' => 'nullable|numeric|min:0',
            'cost_price' => 'nullable|numeric|min:0',
            'low_stock_threshold' => 'required|integer|min:0',
            'expiry_date' => 'nullable|date',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
            'is_best_seller' => 'boolean',
            'is_new_arrival' => 'boolean',
        ]);

        if (empty($validated['slug'])) {
            $validated['slug'] = Str::slug($validated['name_en']);
        }

        // If user cannot edit cost price, retain existing cost price
        if (!$canEditCostPrice) {
            $validated['cost_price'] = $product->cost_price;
        }

        $old = $product->only(['price', 'compare_at_price', 'is_active', 'name_en']);
        $product->update($validated);

        AuditLog::log('product_updated', $product, $old, $product->only(['price', 'compare_at_price', 'is_active', 'name_en']));

        return redirect()->route('admin.products.index')->with('success', 'Product updated successfully.');
    }

    /**
     * Upload an additional image for the product.
     */
    public function uploadImage(Request $request, Product $product): RedirectResponse
    {
        $request->validate([
            'image' => 'required|image|mimes:jpeg,png,webp,jpg|max:2048',
        ]);

        $path = $request->file('image')->store('products', 'public');
        $hasExisting = $product->images()->exists();

        ProductImage::create([
            'product_id' => $product->id,
            'path' => $path,
            'alt_text' => $product->name_en,
            'sort_order' => $product->images()->count(),
            'is_primary' => !$hasExisting, // Make primary if first image
        ]);

        return back()->with('success', 'Image uploaded successfully.');
    }

    /**
     * Set a product image as primary.
     */
    public function setPrimaryImage(Product $product, ProductImage $image): RedirectResponse
    {
        if ($image->product_id !== $product->id) {
            abort(403);
        }

        $product->images()->update(['is_primary' => false]);
        $image->update(['is_primary' => true]);

        return back()->with('success', 'Primary image updated.');
    }

    /**
     * Delete a product image safely.
     */
    public function deleteImage(Product $product, ProductImage $image): RedirectResponse
    {
        if ($image->product_id !== $product->id) {
            abort(403);
        }

        // Delete from public disk
        Storage::disk('public')->delete($image->path);
        $wasPrimary = $image->is_primary;
        $image->delete();

        // If the deleted image was primary, appoint next available image as primary
        if ($wasPrimary) {
            $next = $product->images()->first();
            $next?->update(['is_primary' => true]);
        }

        return back()->with('success', 'Image removed successfully.');
    }
}

