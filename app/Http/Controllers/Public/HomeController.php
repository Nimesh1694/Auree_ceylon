<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    /**
     * Display the public cosmetic storefront home page.
     */
    public function index(Request $request): Response
    {
        $categories = Category::where('is_active', true)
            ->orderBy('sort_order')
            ->take(6)
            ->get();

        $featuredProducts = Product::with('category')
            ->where('is_active', true)
            ->where('is_featured', true)
            ->take(4)
            ->get();

        $bestSellers = Product::with('category')
            ->where('is_active', true)
            ->where('is_best_seller', true)
            ->take(4)
            ->get();

        $newArrivals = Product::with('category')
            ->where('is_active', true)
            ->where('is_new_arrival', true)
            ->take(4)
            ->get();

        return Inertia::render('Welcome', [
            'categories' => $categories,
            'featuredProducts' => $featuredProducts,
            'bestSellers' => $bestSellers,
            'newArrivals' => $newArrivals,
        ]);
    }
}

