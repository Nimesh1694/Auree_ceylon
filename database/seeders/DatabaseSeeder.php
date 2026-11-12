<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\AgentProfile;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Setting;
use App\Models\User;
use App\Services\AgentHierarchyService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Always initialize baseline RBAC roles and permissions idempotently across all environments
        $this->call(RolesAndPermissionsSeeder::class);
        $this->call(InquiryPermissionSeeder::class);

        // Strictly block demo accounts, hardcoded development passwords, and sample catalog outside local/testing
        if (!app()->environment(['local', 'testing'])) {
            return;
        }

        $hierarchyService = app(AgentHierarchyService::class);

        // 1. Settings
        Setting::set('company_name', 'Aura Luxe Cosmetics (Pvt) Ltd', 'general', 'string', 'Official business name');
        Setting::set('company_phone', '+94 11 234 5678', 'general', 'string', 'Customer hotline');
        Setting::set('company_email', 'care@auraluxe.lk', 'general', 'string', 'Support email');
        Setting::set('direct_agent_commission_percent', 25.0, 'commission', 'float', 'Default commission for agent who sells directly');
        Setting::set('upline_agent_commission_percent', 10.0, 'commission', 'float', 'Default commission for high-level upline agent');
        Setting::set('max_commission_percent_per_order', 35.0, 'commission', 'float', 'Configurable safety cap on total commission per order');
        Setting::set('commission_return_period_days', 7, 'commission', 'integer', 'Waiting period in days after delivery before approval');
        Setting::set('default_delivery_charge', 350.00, 'shipping', 'float', 'Standard islandwide COD delivery fee');

        // 2. Users: Management
        $owner = User::firstOrCreate(['email' => 'owner@auraluxe.lk'], [
            'name' => 'Dilshan Ranasinghe (Owner)',
            'phone' => '0771234567',
            'role' => UserRole::OWNER,
            'status' => UserStatus::ACTIVE,
            'password' => Hash::make('Password123!'),
            'email_verified_at' => now(),
        ]);

        $marketing = User::firstOrCreate(['email' => 'marketing@auraluxe.lk'], [
            'name' => 'Kavindi Perera (Marketing)',
            'phone' => '0772345678',
            'role' => UserRole::MARKETING_MANAGER,
            'status' => UserStatus::ACTIVE,
            'password' => Hash::make('Password123!'),
            'email_verified_at' => now(),
        ]);

        $it = User::firstOrCreate(['email' => 'it@auraluxe.lk'], [
            'name' => 'Naveen Jayawardena (Super Admin)',
            'phone' => '0773456789',
            'role' => UserRole::SUPER_ADMIN,
            'status' => UserStatus::ACTIVE,
            'password' => Hash::make('Password123!'),
            'email_verified_at' => now(),
        ]);

        $accountant = User::firstOrCreate(['email' => 'accountant@auraluxe.lk'], [
            'name' => 'Chamari Silva (Accountant)',
            'phone' => '0774567890',
            'role' => UserRole::ACCOUNTANT,
            'status' => UserStatus::ACTIVE,
            'password' => Hash::make('Password123!'),
            'email_verified_at' => now(),
        ]);

        $prodInv = User::firstOrCreate(['email' => 'inventory@auraluxe.lk'], [
            'name' => 'Dhanushka Perera (Product & Inventory Manager)',
            'phone' => '0775678901',
            'role' => UserRole::PRODUCT_INVENTORY_MANAGER,
            'status' => UserStatus::ACTIVE,
            'password' => Hash::make('Password123!'),
            'email_verified_at' => now(),
        ]);

        $operations = User::firstOrCreate(['email' => 'operations@auraluxe.lk'], [
            'name' => 'Roshan Wickramasinghe (Operations Manager)',
            'phone' => '0776789012',
            'role' => UserRole::OPERATIONS_MANAGER,
            'status' => UserStatus::ACTIVE,
            'password' => Hash::make('Password123!'),
            'email_verified_at' => now(),
        ]);

        $agentManager = User::firstOrCreate(['email' => 'agent.manager@auraluxe.lk'], [
            'name' => 'Malini Senanayake (Agent Network Manager)',
            'phone' => '0777890123',
            'role' => UserRole::AGENT_MANAGER,
            'status' => UserStatus::ACTIVE,
            'password' => Hash::make('Password123!'),
            'email_verified_at' => now(),
        ]);

        // 3. Users: Agent Network (High-level & Low-level)
        $highAgent = User::firstOrCreate(['email' => 'agent.level1@auraluxe.lk'], [
            'name' => 'Sanduni Wijesinghe (High-level Agent)',
            'phone' => '0715678901',
            'role' => UserRole::HIGH_LEVEL_AGENT,
            'status' => UserStatus::ACTIVE,
            'referral_code' => 'AGTHIGH01',
            'password' => Hash::make('Password123!'),
            'email_verified_at' => now(),
        ]);
        AgentProfile::firstOrCreate(['user_id' => $highAgent->id], [
            'business_name' => 'Sanduni Glow Studio',
            'nic' => '199256789012',
            'bank_name' => 'Commercial Bank',
            'bank_branch' => 'Colombo Fort',
            'bank_account_number' => '1002345678',
            'bank_account_name' => 'S. Wijesinghe',
            'kyc_status' => 'verified',
        ]);
        $hierarchyService->attachAgent($highAgent, null);

        $lowAgent = User::firstOrCreate(['email' => 'agent.level2@auraluxe.lk'], [
            'name' => 'Tharushi Fernando (Low-level Agent)',
            'phone' => '0716789012',
            'role' => UserRole::LOW_LEVEL_AGENT,
            'status' => UserStatus::ACTIVE,
            'referral_code' => 'AGTLOW01',
            'password' => Hash::make('Password123!'),
            'email_verified_at' => now(),
        ]);
        AgentProfile::firstOrCreate(['user_id' => $lowAgent->id], [
            'business_name' => 'Tharu Beauty Hub',
            'nic' => '199856789034',
            'bank_name' => 'Sampath Bank',
            'bank_branch' => 'Kandy City',
            'bank_account_number' => '0023456789',
            'bank_account_name' => 'T. Fernando',
            'kyc_status' => 'verified',
        ]);
        $hierarchyService->attachAgent($lowAgent, $highAgent);

        // 4. Users: Customer
        User::firstOrCreate(['email' => 'customer@example.com'], [
            'name' => 'Anura Bandara',
            'phone' => '0781234567',
            'role' => UserRole::CUSTOMER,
            'status' => UserStatus::ACTIVE,
            'password' => Hash::make('Password123!'),
            'email_verified_at' => now(),
        ]);

        // 5. Product Categories
        $serums = Category::firstOrCreate(['slug' => 'facial-serums'], [
            'name_en' => 'Facial Serums & Elixirs',
            'name_si' => 'මුහුණේ සීරම් සහ එලික්සර්',
            'description_en' => 'Intensive botanical concentrates designed for targeted skin renewal.',
            'description_si' => 'සම පුනර්ජීවනය කරන තීව්‍ර ස්වභාවික සාරය සහිත සීරම්.',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $lipsticks = Category::firstOrCreate(['slug' => 'luxury-lipsticks'], [
            'name_en' => 'Luxury Lipsticks',
            'name_si' => 'සුඛෝපභෝගී ලිප්ස්ටික්',
            'description_en' => 'Hydrating satin and velvet matte shades enriched with jojoba and shea.',
            'description_si' => 'ජොජෝබා සහ ෂියා බටර් අඩංගු තෙතමනය රඳවන ලිප්ස්ටික්.',
            'sort_order' => 2,
            'is_active' => true,
        ]);

        $moisturizers = Category::firstOrCreate(['slug' => 'moisturizers-creams'], [
            'name_en' => 'Moisturizers & Creams',
            'name_si' => 'මොයිස්චරයිසර් සහ ක්‍රීම්',
            'description_en' => 'Deeply nourishing barrier hydration for long-lasting, dewy glow.',
            'description_si' => 'නීරෝගී දිදුලන සමක් සඳහා ගැඹුරු තෙතමනය සපයන ආලේපන.',
            'sort_order' => 3,
            'is_active' => true,
        ]);

        // 6. Real database-backed Products
        $p1 = Product::firstOrCreate(['slug' => 'golden-radiance-vitamin-c-serum'], [
            'category_id' => $serums->id,
            'name_en' => 'Golden Radiance 15% Vitamin C Serum',
            'name_si' => 'ගෝල්ඩන් රේඩියන්ස් විටමින් C සීරම් 15%',
            'sku' => 'AL-SER-001',
            'short_description_en' => 'Brightens dark spots and visibly boosts skin glow in 14 days.',
            'short_description_si' => 'ලප කැළැල් ඉවත් කර සමට ස්වභාවික දීප්තියක් ලබාදෙයි.',
            'description_en' => 'Our flagship antioxidant elixir formulated with 15% pure ethyl ascorbic acid, ferulic acid, and organic Ceylon green tea extract. Defends against oxidative stress and restores natural elasticity.',
            'description_si' => '15% පිරිසිදු විටමින් C සහ ලංකාවේ හරිත තේ සාරය සංකලනයෙන් නිපදවා ඇත. සමේ රැළි වැටීම් අවම කර නැවුම් පෙනුමක් ලබාදේ.',
            'ingredients_en' => 'Aqua, 3-O-Ethyl Ascorbic Acid, Ferulic Acid, Ceylon Green Tea Extract, Sodium Hyaluronate, Tocopherol (Vitamin E), Glycerin.',
            'ingredients_si' => 'ජලය, 3-O-ඊතයිල් ඇස්කෝර්බික් අම්ලය, ෆෙරුලික් අම්ලය, හරිත තේ සාරය, සෝඩියම් හයලුරොනේට්, විටමින් E.',
            'directions_en' => 'Apply 3-4 drops morning and evening onto cleansed face and neck before moisturizer. Use sunscreen during the day.',
            'directions_si' => 'පිරිසිදු කරගත් මුහුණට සහ බෙල්ලට උදේ සවස බිංදු 3-4 ක් ආලේප කරන්න.',
            'price' => 4850.00,
            'compare_at_price' => 5400.00,
            'cost_price' => 2200.00,
            'stock_quantity' => 120,
            'low_stock_threshold' => 10,
            'is_active' => true,
            'is_featured' => true,
            'is_best_seller' => true,
            'is_new_arrival' => false,
        ]);

        $p2 = Product::firstOrCreate(['slug' => 'velvet-rose-matte-lipstick'], [
            'category_id' => $lipsticks->id,
            'name_en' => 'Velvet Rose Hydrating Matte Lipstick',
            'name_si' => 'වෙල්වට් රෝස් හයිඩ්‍රේටින් මැට් ලිප්ස්ටික්',
            'sku' => 'AL-LIP-002',
            'short_description_en' => '12-hour comfortable velvet matte finish with organic jojoba.',
            'short_description_si' => 'පැය 12 ක් පවතින සුවපහසු මැට් වර්ණය සහ තොල්වල තෙතමනය රැකදේ.',
            'description_en' => 'An ultra-pigmented yet weightless matte lipstick that glides effortlessly. Infused with organic virgin coconut oil and wild rose petal wax to keep lips supple all day.',
            'description_si' => 'තොල් වියලීමකින් තොරව දිගු වේලාවක් පවතින ස්වභාවික ආකර්ශනීය වර්ණය.',
            'ingredients_en' => 'Ricinus Communis Seed Oil, Candelilla Wax, Simmondsia Chinensis (Jojoba) Oil, Butyrospermum Parkii (Shea) Butter, CI 77491.',
            'ingredients_si' => 'එඬරු තෙල්, කැන්ඩලිල්ලා ඉටි, ජොජෝබා තෙල්, ෂියා බටර්, ස්වභාවික වර්ණක.',
            'directions_en' => 'Glide directly from bullet onto clean, moisturized lips. Layer for richer intensity.',
            'directions_si' => 'පිරිසිදු තොල් මත කෙලින්ම ආලේප කරන්න.',
            'price' => 2950.00,
            'compare_at_price' => 3400.00,
            'cost_price' => 1100.00,
            'stock_quantity' => 85,
            'low_stock_threshold' => 10,
            'is_active' => true,
            'is_featured' => true,
            'is_best_seller' => true,
            'is_new_arrival' => true,
        ]);

        $p3 = Product::firstOrCreate(['slug' => 'hydra-glow-botanical-day-cream'], [
            'category_id' => $moisturizers->id,
            'name_en' => 'Hydra-Glow Botanical Day Barrier Cream',
            'name_si' => 'හයිඩ්‍රා ග්ලෝ ඩේ ක්‍රීම් 50g',
            'sku' => 'AL-CRE-003',
            'short_description_en' => 'Deeply replenishing daily moisture barrier with gotu kola and squalane.',
            'short_description_si' => 'ගොටුකොළ සාරය සහ ස්කුවාලේන් අඩංගු දිනපතා දිදුලන සමක් ලබාදෙන ක්‍රීම්.',
            'description_en' => 'Formulated for tropical climates, this fast-absorbing gel-cream fortifies the skin barrier against environmental moisture loss. Leaves a silky satin glow without grease.',
            'description_si' => 'සමේ තෙතමනය ආරක්‍ෂා කරමින් දිනය පුරා ප්‍රබෝධමත් සුමුදු බවක් ලබාදෙයි.',
            'ingredients_en' => 'Centella Asiatica (Gotu Kola) Extract, Plant-derived Squalane, Niacinamide (5%), Panthenol, Camellia Oleifera Seed Oil.',
            'ingredients_si' => 'ගොටුකොළ සාරය, ස්කුවාලේන්, නියැසිනමයිඩ් (5%), පැන්තෙනෝල්, ජලය.',
            'directions_en' => 'Gently massage a pea-sized amount over face and neck each morning after cleansing.',
            'directions_si' => 'සෑම දිනකම උදෑසන සුළු ප්‍රමාණයක් මුහුණේ සහ බෙල්ලේ ආලේප කරන්න.',
            'price' => 3750.00,
            'compare_at_price' => 4200.00,
            'cost_price' => 1600.00,
            'stock_quantity' => 150,
            'low_stock_threshold' => 15,
            'is_active' => true,
            'is_featured' => false,
            'is_best_seller' => false,
            'is_new_arrival' => true,
        ]);
    }
}
