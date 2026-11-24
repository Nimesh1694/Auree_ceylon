<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class AdminBannerController extends Controller
{
    /**
     * Display a listing of storefront banners for Marketing Manager.
     */
    public function index(Request $request): Response
    {
        $query = Banner::query();

        // Filter by placement
        if ($request->filled('placement') && in_array($request->placement, ['hero_slider', 'sub_banner_1', 'sub_banner_2', 'trust_badge'])) {
            $query->where('placement', $request->placement);
        }

        // Filter by search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title_en', 'like', "%{$search}%")
                    ->orWhere('title_si', 'like', "%{$search}%")
                    ->orWhere('subtitle_en', 'like', "%{$search}%");
            });
        }

        // Filter by schedule status
        if ($request->filled('status')) {
            $now = Carbon::now();
            switch ($request->status) {
                case 'active':
                    $query->where('is_active', true)
                        ->where(function ($q) use ($now) {
                            $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
                        })
                        ->where(function ($q) use ($now) {
                            $q->whereNull('ends_at')->orWhere('ends_at', '>=', $now);
                        });
                    break;
                case 'scheduled':
                    $query->where('is_active', true)
                        ->whereNotNull('starts_at')
                        ->where('starts_at', '>', $now);
                    break;
                case 'expired':
                    $query->where('is_active', true)
                        ->whereNotNull('ends_at')
                        ->where('ends_at', '<', $now);
                    break;
                case 'draft':
                    $query->where('is_active', false);
                    break;
            }
        }

        $banners = $query->orderBy('placement')
            ->orderBy('sort_order')
            ->orderByDesc('created_at')
            ->get();

        // Summary counts
        $now = Carbon::now();
        $counts = [
            'total' => Banner::count(),
            'active' => Banner::where('is_active', true)
                ->where(function ($q) use ($now) {
                    $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
                })
                ->where(function ($q) use ($now) {
                    $q->whereNull('ends_at')->orWhere('ends_at', '>=', $now);
                })->count(),
            'scheduled' => Banner::where('is_active', true)
                ->whereNotNull('starts_at')
                ->where('starts_at', '>', $now)->count(),
            'hero_slider' => Banner::where('placement', 'hero_slider')->count(),
            'sub_banners' => Banner::whereIn('placement', ['sub_banner_1', 'sub_banner_2'])->count(),
            'trust_badges' => Banner::where('placement', 'trust_badge')->count(),
        ];

        // Storefront Experience Section (User Comments vs Featured Video)
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
            'autoplay' => true,
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

        $whatsappSettings = [
            'number' => (string) Setting::get('whatsapp_number', env('VITE_WHATSAPP_NUMBER', '94772345678')),
            'message' => (string) Setting::get('whatsapp_message', 'Hello Aura Luxe, I would like to inquire about your products.'),
            'is_enabled' => (bool) Setting::get('whatsapp_enabled', true),
        ];

        return Inertia::render('Admin/Banners/Index', [
            'banners' => $banners,
            'filters' => $request->only(['placement', 'status', 'search']),
            'counts' => $counts,
            'experienceMode' => $experienceMode,
            'experienceVideo' => $experienceVideo,
            'experienceReviews' => $experienceReviews,
            'whatsappSettings' => $whatsappSettings,
        ]);
    }

    /**
     * Update Storefront Experience Section mode and settings (Comments vs Video).
     */
    public function updateExperience(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'mode' => 'required|in:reviews,video',
            'video_url' => 'nullable|string|max:1000',
            'video_type' => 'nullable|in:youtube,vimeo,mp4',
            'video_eyebrow_en' => 'nullable|string|max:255',
            'video_eyebrow_si' => 'nullable|string|max:255',
            'video_title_en' => 'nullable|string|max:255',
            'video_title_si' => 'nullable|string|max:255',
            'video_description_en' => 'nullable|string|max:2000',
            'video_description_si' => 'nullable|string|max:2000',
            'video_cta_text_en' => 'nullable|string|max:255',
            'video_cta_text_si' => 'nullable|string|max:255',
            'video_cta_url' => 'nullable|string|max:500',
            'video_autoplay' => 'nullable|boolean',
            'poster' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:5120',
            'reviews_eyebrow_en' => 'nullable|string|max:255',
            'reviews_eyebrow_si' => 'nullable|string|max:255',
            'reviews_title_en' => 'nullable|string|max:255',
            'reviews_title_si' => 'nullable|string|max:255',
            'reviews' => 'nullable|array',
        ]);

        Setting::set('storefront_experience_mode', $validated['mode'], 'storefront', 'string');

        $existingVideo = Setting::get('storefront_experience_video', []);
        if (!is_array($existingVideo)) $existingVideo = [];

        $posterPath = $existingVideo['poster_image'] ?? null;
        if ($request->hasFile('poster')) {
            if ($posterPath && Storage::disk('public')->exists($posterPath)) {
                Storage::disk('public')->delete($posterPath);
            }
            $posterPath = $request->file('poster')->store('banners', 'public');
        }

        $videoData = [
            'video_url' => $validated['video_url'] ?? ($existingVideo['video_url'] ?? 'https://www.youtube.com/watch?v=2e_Z2WzY9s4'),
            'video_type' => $validated['video_type'] ?? 'youtube',
            'eyebrow_en' => $validated['video_eyebrow_en'] ?? ($existingVideo['eyebrow_en'] ?? 'THE ROYAL CEYLON RITUAL'),
            'eyebrow_si' => $validated['video_eyebrow_si'] ?? ($existingVideo['eyebrow_si'] ?? 'රාජකීය ශාකසාර අත්දැකීම'),
            'title_en' => $validated['video_title_en'] ?? ($existingVideo['title_en'] ?? 'Behind the Royal Ceylon Botanical Science'),
            'title_si' => $validated['video_title_si'] ?? ($existingVideo['title_si'] ?? 'අපගේ රාජකීය ශාකසාර රූපලාවණ්‍ය ක්‍රමවේදය'),
            'description_en' => $validated['video_description_en'] ?? ($existingVideo['description_en'] ?? ''),
            'description_si' => $validated['video_description_si'] ?? ($existingVideo['description_si'] ?? ''),
            'cta_text_en' => $validated['video_cta_text_en'] ?? ($existingVideo['cta_text_en'] ?? 'Explore The Botanical Rituals'),
            'cta_text_si' => $validated['video_cta_text_si'] ?? ($existingVideo['cta_text_si'] ?? 'රාජකීය එකතුව නරඹන්න'),
            'cta_url' => $validated['video_cta_url'] ?? ($existingVideo['cta_url'] ?? '/products'),
            'poster_image' => $posterPath,
            'autoplay' => $request->has('video_autoplay') ? $request->boolean('video_autoplay') : ($existingVideo['autoplay'] ?? true),
        ];

        Setting::set('storefront_experience_video', $videoData, 'storefront', 'json');

        if ($request->filled('reviews') && is_array($request->reviews)) {
            Setting::set('storefront_experience_reviews', [
                'eyebrow_en' => $validated['reviews_eyebrow_en'] ?? 'VERIFIED CEYLON EXPERIENCES',
                'eyebrow_si' => $validated['reviews_eyebrow_si'] ?? 'තහවුරු කළ අත්දැකීම්',
                'title_en' => $validated['reviews_title_en'] ?? 'Loved by Discerning Women & Men Across Sri Lanka',
                'title_si' => $validated['reviews_title_si'] ?? 'ශ්‍රී ලාංකික කාන්තා හා පිරිමි ප්‍රජාවගේ ආදරය දිනූ',
                'items' => $request->reviews,
            ], 'storefront', 'json');
        }

        $activeLabel = $validated['mode'] === 'video' ? 'Featured Video' : 'Customer Reviews / Comments';
        return back()->with('success', "Experience section updated successfully. Active Mode: {$activeLabel}");
    }

    /**
     * Store a newly created banner.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title_en' => ['required', 'string', 'max:255'],
            'title_si' => ['nullable', 'string', 'max:255'],
            'subtitle_en' => ['nullable', 'string'],
            'subtitle_si' => ['nullable', 'string'],
            'cta_text_en' => ['nullable', 'string', 'max:100'],
            'cta_text_si' => ['nullable', 'string', 'max:100'],
            'cta_url' => ['nullable', 'string', 'max:255'],
            'placement' => ['required', 'in:hero_slider,sub_banner_1,sub_banner_2,trust_badge'],
            'theme_style' => ['nullable', 'string', 'max:100'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'is_active' => ['boolean'],
            'sort_order' => ['nullable', 'integer'],
            'image' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp,svg', 'max:5120'],
        ]);

        $data = $validated;
        unset($data['image']);

        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('banners', 'public');
            $data['image_path'] = $path;
        }

        $data['theme_style'] = $data['theme_style'] ?? 'festive_rose';
        $data['is_active'] = $request->boolean('is_active', true);
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);

        Banner::create($data);

        return redirect()->back()->with('success', 'Banner created and scheduled successfully.');
    }

    /**
     * Update the specified banner.
     */
    public function update(Request $request, Banner $banner): RedirectResponse
    {
        $validated = $request->validate([
            'title_en' => ['required', 'string', 'max:255'],
            'title_si' => ['nullable', 'string', 'max:255'],
            'subtitle_en' => ['nullable', 'string'],
            'subtitle_si' => ['nullable', 'string'],
            'cta_text_en' => ['nullable', 'string', 'max:100'],
            'cta_text_si' => ['nullable', 'string', 'max:100'],
            'cta_url' => ['nullable', 'string', 'max:255'],
            'placement' => ['required', 'in:hero_slider,sub_banner_1,sub_banner_2,trust_badge'],
            'theme_style' => ['nullable', 'string', 'max:100'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'is_active' => ['boolean'],
            'sort_order' => ['nullable', 'integer'],
            'image' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp,svg', 'max:5120'],
        ]);

        $data = $validated;
        unset($data['image']);

        if ($request->hasFile('image')) {
            if ($banner->image_path && Storage::disk('public')->exists($banner->image_path)) {
                Storage::disk('public')->delete($banner->image_path);
            }
            $path = $request->file('image')->store('banners', 'public');
            $data['image_path'] = $path;
        }

        $data['is_active'] = $request->boolean('is_active', $banner->is_active);
        $data['sort_order'] = (int) ($data['sort_order'] ?? $banner->sort_order);

        $banner->update($data);

        return redirect()->back()->with('success', 'Banner schedule and details updated successfully.');
    }

    /**
     * Toggle the active status of a banner.
     */
    public function toggle(Banner $banner): RedirectResponse
    {
        $banner->update(['is_active' => ! $banner->is_active]);

        $status = $banner->is_active ? 'activated' : 'deactivated';

        return redirect()->back()->with('success', "Banner {$status} successfully.");
    }

    /**
     * Remove the specified banner.
     */
    public function destroy(Banner $banner): RedirectResponse
    {
        if ($banner->image_path && Storage::disk('public')->exists($banner->image_path)) {
            Storage::disk('public')->delete($banner->image_path);
        }

        $banner->delete();

        return redirect()->back()->with('success', 'Banner deleted successfully.');
    }

    /**
     * Update customer support WhatsApp number and options for Marketing Manager.
     */
    public function updateWhatsApp(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'whatsapp_number' => ['required', 'string', 'max:30'],
            'whatsapp_message' => ['nullable', 'string', 'max:500'],
            'is_enabled' => ['nullable', 'boolean'],
        ]);

        $rawNumber = trim($validated['whatsapp_number']);
        // Strip non-digits
        $cleanNumber = preg_replace('/[^0-9]/', '', $rawNumber);

        // Convert local SL format (07XXXXXXXX) to international (947XXXXXXXX)
        if (str_starts_with($cleanNumber, '0') && strlen($cleanNumber) === 10) {
            $cleanNumber = '94' . substr($cleanNumber, 1);
        }

        Setting::set('whatsapp_number', $cleanNumber, 'marketing', 'string', 'Public WhatsApp support hotline');
        Setting::set('whatsapp_message', $validated['whatsapp_message'] ?? 'Hello Aura Luxe, I would like to inquire about your products.', 'marketing', 'string', 'Default pre-filled WhatsApp greeting');
        Setting::set('whatsapp_enabled', $request->boolean('is_enabled', true), 'marketing', 'boolean', 'Whether floating WhatsApp button is active');

        return redirect()->back()->with('success', "WhatsApp contact number updated to +{$cleanNumber} successfully.");
    }
}

