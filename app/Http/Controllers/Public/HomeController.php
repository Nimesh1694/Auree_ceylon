<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use App\Models\Category;
use App\Models\Product;
use App\Models\Setting;
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
            ->withCount(['products' => fn ($q) => $q->where('is_active', true)])
            ->orderBy('sort_order')
            ->get();

        $allProducts = Product::with(['category', 'primaryImage', 'images'])
            ->where('is_active', true)
            ->latest('id')
            ->take(16)
            ->get();

        $featuredProducts = Product::with(['category', 'primaryImage', 'images'])
            ->where('is_active', true)
            ->where('is_featured', true)
            ->take(8)
            ->get();

        $bestSellers = Product::with(['category', 'primaryImage', 'images'])
            ->where('is_active', true)
            ->where('is_best_seller', true)
            ->take(8)
            ->get();

        $newArrivals = Product::with(['category', 'primaryImage', 'images'])
            ->where('is_active', true)
            ->where('is_new_arrival', true)
            ->take(8)
            ->get();

        // 3-Banner Strategy (Spa Ceylon Luxury Style): Hero Swappable Slider + 2 Sub-Banners
        $heroBanners = Banner::currentlyLive()
            ->forPlacement('hero_slider')
            ->orderBy('sort_order')
            ->orderByDesc('created_at')
            ->get();

        $subBanner1 = Banner::currentlyLive()
            ->forPlacement('sub_banner_1')
            ->orderBy('sort_order')
            ->first();

        $subBanner2 = Banner::currentlyLive()
            ->forPlacement('sub_banner_2')
            ->orderBy('sort_order')
            ->first();

        // Manageable Royal Trust Badges
        $trustBadges = Banner::currentlyLive()
            ->forPlacement('trust_badge')
            ->orderBy('sort_order')
            ->get();

        // Manageable Storefront Experience Section (Reviews vs Video Mode)
        $experienceMode = Setting::get('storefront_experience_mode', 'reviews');
        $experienceVideo = Setting::get('storefront_experience_video', [
            'video_url' => 'https://www.youtube.com/watch?v=2e_Z2WzY9s4',
            'video_type' => 'youtube',
            'eyebrow_en' => 'THE ROYAL CEYLON RITUAL',
            'eyebrow_si' => 'රාජකීය ශාකසාර අත්දැකීම',
            'title_en' => 'Behind the Royal Ceylon Botanical Science',
            'title_si' => 'අපගේ රාජකීය ශාකසාර රූපලාවණ්‍ය ක්‍රමවේදය',
            'description_en' => 'Immerse yourself in our centuries-old cold-pressed distillation rituals, sustainably harvested from Sri Lanka’s lush botanical reserves.',
            'description_si' => 'ශ්‍රී ලංකාවේ සාරවත් ඖෂධ උයන්වලින් තිරසාර ලෙස ලබාගත් පිරිසිදු ස්වභාවික සාරයෙන් අප නිපදවන සත්කාර නරඹන්න.',
            'cta_text_en' => 'Explore The Botanical Rituals',
            'cta_text_si' => 'රාජකීය එකතුව නරඹන්න',
            'cta_url' => '/products',
            'poster_image' => null,
        ]);
        $experienceReviews = Setting::get('storefront_experience_reviews', [
            'eyebrow_en' => 'VERIFIED CEYLON EXPERIENCES',
            'eyebrow_si' => 'තහවුරු කළ අත්දැකීම්',
            'title_en' => 'Loved by Discerning Women & Men Across Sri Lanka',
            'title_si' => 'ශ්‍රී ලාංකික කාන්තා හා පිරිමි ප්‍රජාවගේ ආදරය දිනූ',
            'items' => [
                [
                    'name' => 'Nipuni Senanayake',
                    'location' => 'Kandy • Verified Buyer',
                    'rating' => 5,
                    'comment' => 'The Saffron Radiance Serum has transformed my skin texture completely. The authentic herbal aroma reminds me of royal Sri Lankan spas. Plus, COD courier delivered to Kandy in just 2 days!',
                ],
                [
                    'name' => 'Dr. Hansi Fernando',
                    'location' => 'Colombo 07 • Verified Buyer',
                    'rating' => 5,
                    'comment' => 'AURA LUXE products feel genuinely luxurious. No sticky residue or harsh synthetic perfume. Knowing it’s 100% vegetarian and free from parabens gives me total peace of mind.',
                ],
                [
                    'name' => 'Malik Jayawardena',
                    'location' => 'Galle • Verified Buyer',
                    'rating' => 5,
                    'comment' => 'The Cash on Delivery service was flawless. The package arrived in regal packaging with botanical care notes. Truly on par with international luxury brands.',
                ],
            ],
        ]);

        return Inertia::render('Welcome', [
            'categories' => $categories,
            'allProducts' => $allProducts,
            'featuredProducts' => $featuredProducts,
            'bestSellers' => $bestSellers,
            'newArrivals' => $newArrivals,
            'heroBanners' => $heroBanners,
            'subBanner1' => $subBanner1,
            'subBanner2' => $subBanner2,
            'trustBadges' => $trustBadges,
            'experienceMode' => $experienceMode,
            'experienceVideo' => $experienceVideo,
            'experienceReviews' => $experienceReviews,
        ]);
    }
}

