<?php

namespace Database\Seeders;

use App\Models\Banner;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class BannerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Hero Slide 1 (Festive Gallery from user screenshot)
        Banner::updateOrCreate(
            ['title_en' => 'ONLINE FESTIVE GALLERY IS NOW LIVE', 'placement' => 'hero_slider'],
            [
                'title_si' => 'රාජකීය උත්සව ගැලරිය දැන් විවෘතයි',
                'subtitle_en' => 'Step into our immersive Ceylon botanical wonderland. Exclusive festive gift sets and limited edition royal formulations.',
                'subtitle_si' => 'සුවිශේෂී රාජකීය ආයුර්වේද සත්කාර සහ තිළිණ පෙට්ටි එකතුව අදම ලබාගන්න.',
                'cta_text_en' => 'EXPERIENCE NOW',
                'cta_text_si' => 'දැන්ම නරඹන්න',
                'cta_url' => '/products',
                'theme_style' => 'festive_rose',
                'is_active' => true,
                'starts_at' => Carbon::now()->subDays(1),
                'ends_at' => Carbon::now()->addMonths(3),
                'sort_order' => 1,
            ]
        );

        // 2. Hero Slide 2 (Royal Radiance Collection)
        Banner::updateOrCreate(
            ['title_en' => 'ROYAL CEYLON RADIANCE RITUALS', 'placement' => 'hero_slider'],
            [
                'title_si' => 'රාජකීය ශ්‍රී ලාංකික සත්කාර චාරිත්‍ර',
                'subtitle_en' => 'Hand-blended with organic central highland botanicals, saffron, and wild rose petals for eternal luminous glow.',
                'subtitle_si' => 'ස්වභාවික කුංකුම සහ රෝස සාරයෙන් සමට ලබාදෙන අසමසම දීප්තිමත් රැකවරණය.',
                'cta_text_en' => 'SHOP THE COLLECTION',
                'cta_text_si' => 'එකතුව බලන්න',
                'cta_url' => '/products',
                'theme_style' => 'royal_gold',
                'is_active' => true,
                'starts_at' => Carbon::now()->subDays(1),
                'ends_at' => Carbon::now()->addMonths(3),
                'sort_order' => 2,
            ]
        );

        // 3. Sub-Banner 1 (Bottom Left: Journey Lighter / Social Care from user screenshot)
        Banner::updateOrCreate(
            ['placement' => 'sub_banner_1'],
            [
                'title_en' => 'MAKE THEIR JOURNEY LIGHTER',
                'title_si' => 'සුවපත් දිවියකට ශාකසාර අත්වැලක්',
                'subtitle_en' => 'What if you could make one month of someone’s cancer journey a little easier?',
                'subtitle_si' => 'රෝගී සත්කාර සහ ප්‍රජා සුවතා වැඩසටහන සමඟ අත්වැල් බැඳගන්න.',
                'cta_text_en' => 'LEARN MORE',
                'cta_text_si' => 'වැඩිදුර විස්තර',
                'cta_url' => '/about',
                'theme_style' => 'dark_botanical',
                'is_active' => true,
                'starts_at' => Carbon::now()->subDays(1),
                'ends_at' => Carbon::now()->addMonths(3),
                'sort_order' => 1,
            ]
        );

        // 4. Sub-Banner 2 (Bottom Right: Ready, Set, Glow from user screenshot)
        Banner::updateOrCreate(
            ['placement' => 'sub_banner_2'],
            [
                'title_en' => 'READY, SET, GLOW',
                'title_si' => 'සමට නව දීප්තියක්',
                'subtitle_en' => 'WITH THE SERUM MADE FOR YOU. Infused with potent Ceylon antioxidants.',
                'subtitle_si' => 'ඔබේ සමට ගැළපෙනම ස්වභාවික සීරම් එකතුව.',
                'cta_text_en' => 'SHOP NOW',
                'cta_text_si' => 'මිලදී ගන්න',
                'cta_url' => '/products',
                'theme_style' => 'ivory_cream',
                'is_active' => true,
                'starts_at' => Carbon::now()->subDays(1),
                'ends_at' => Carbon::now()->addMonths(3),
                'sort_order' => 1,
            ]
        );

        // 5. Four Royal Trust Seals (Manageable & Extensible by Marketing Manager)
        $trustBadges = [
            [
                'title_en' => '100% Ceylon Botanicals',
                'title_si' => '100% පිරිසිදු ශාකසාර',
                'subtitle_en' => 'No parabens, sulfates or alcohol',
                'subtitle_si' => 'හානිකර රසායනික ද්‍රව්‍ය නැත',
                'theme_style' => '🌿',
                'sort_order' => 1,
            ],
            [
                'title_en' => 'Islandwide COD Delivery',
                'title_si' => 'දිවයින පුරා COD බෙදාහැරීම',
                'subtitle_en' => 'Pay at your doorstep across Sri Lanka',
                'subtitle_si' => 'භාණ්ඩ ලැබුණු පසු මුදල් ගෙවන්න',
                'theme_style' => '🚚',
                'sort_order' => 2,
            ],
            [
                'title_en' => 'Royal Palace Formula',
                'title_si' => 'රාජකීය සූත්‍රය',
                'subtitle_en' => 'Ancient Ceylon beauty science',
                'subtitle_si' => 'පාරම්පරික ආයුර්වේද විද්‍යාව',
                'theme_style' => '👑',
                'sort_order' => 3,
            ],
            [
                'title_en' => '100% Cruelty-Free',
                'title_si' => 'සත්ව හිංසනයෙන් තොරයි',
                'subtitle_en' => 'Never tested on animals & vegetarian',
                'subtitle_si' => 'සත්ත්ව පරීක්ෂණ නොමැත',
                'theme_style' => '🐰',
                'sort_order' => 4,
            ],
        ];

        foreach ($trustBadges as $badge) {
            Banner::updateOrCreate(
                ['title_en' => $badge['title_en'], 'placement' => 'trust_badge'],
                [
                    'title_si' => $badge['title_si'],
                    'subtitle_en' => $badge['subtitle_en'],
                    'subtitle_si' => $badge['subtitle_si'],
                    'theme_style' => $badge['theme_style'],
                    'sort_order' => $badge['sort_order'],
                    'is_active' => true,
                    'starts_at' => Carbon::now()->subDays(1),
                    'ends_at' => Carbon::now()->addYears(5),
                ]
            );
        }
    }
}

