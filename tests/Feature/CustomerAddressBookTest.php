<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\CustomerAddress;
use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerAddressBookTest extends TestCase
{
    use RefreshDatabase;

    private User $customer;
    private User $otherCustomer;
    private User $owner;
    private User $agent;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = User::factory()->create([
            'role' => UserRole::CUSTOMER,
            'status' => UserStatus::ACTIVE,
            'name' => 'Kavindu Senanayake',
            'email' => 'kavindu@example.com',
            'phone' => '0771234567',
        ]);

        $this->otherCustomer = User::factory()->create([
            'role' => UserRole::CUSTOMER,
            'status' => UserStatus::ACTIVE,
            'name' => 'Nadeesha Silva',
            'email' => 'nadeesha@example.com',
            'phone' => '0719876543',
        ]);

        $this->owner = User::factory()->create([
            'role' => UserRole::OWNER,
            'status' => UserStatus::ACTIVE,
            'email' => 'owner@auraluxe.lk',
            'two_factor_confirmed_at' => now(),
        ]);

        $this->agent = User::factory()->create([
            'role' => UserRole::HIGH_LEVEL_AGENT,
            'status' => UserStatus::ACTIVE,
            'email' => 'agent@auraluxe.lk',
        ]);

        Setting::set('default_delivery_charge', 350.00, 'shipping', 'float');
    }

    public function test_guest_users_are_redirected_to_login_when_accessing_address_book(): void
    {
        $response = $this->get(route('customer.addresses.index'));
        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_customer_can_view_address_book(): void
    {
        $address = CustomerAddress::create([
            'user_id' => $this->customer->id,
            'label' => 'Home',
            'recipient_name' => 'Kavindu Senanayake',
            'phone' => '0771234567',
            'address_line_1' => 'No 45, Temple Road',
            'city' => 'Colombo 03',
            'district' => 'Colombo',
            'postal_code' => '00300',
            'is_default' => true,
        ]);

        $response = $this->actingAs($this->customer)->get(route('customer.addresses.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->component('Customer/Addresses/Index')
                ->has('addresses', 1)
                ->where('addresses.0.recipient_name', 'Kavindu Senanayake')
                ->where('addresses.0.city', 'Colombo 03')
                ->where('addresses.0.is_default', true)
                ->where('maxAddresses', 10)
                ->where('addressCount', 1)
        );
    }

    public function test_customer_can_create_new_address_and_first_address_becomes_default_automatically(): void
    {
        $addressData = [
            'label' => 'Home',
            'recipient_name' => 'Kavindu Senanayake',
            'phone' => '0771234567',
            'alternative_phone' => '0112345678',
            'address_line_1' => 'No 10, Flower Road',
            'address_line_2' => 'Apt 2B',
            'city' => 'Colombo 07',
            'district' => 'Colombo',
            'postal_code' => '00700',
            'delivery_instructions' => 'Ring bell twice',
            'is_default' => false, // First address must automatically be promoted to default!
        ];

        $response = $this->actingAs($this->customer)->post(route('customer.addresses.store'), $addressData);

        $response->assertRedirect(route('customer.addresses.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('customer_addresses', [
            'user_id' => $this->customer->id,
            'label' => 'Home',
            'recipient_name' => 'Kavindu Senanayake',
            'city' => 'Colombo 07',
            'district' => 'Colombo',
            'is_default' => true, // Verified auto-default!
        ]);
    }

    public function test_customer_can_add_second_address_and_setting_as_default_demotes_first_address(): void
    {
        // First address (default)
        $firstAddress = CustomerAddress::create([
            'user_id' => $this->customer->id,
            'label' => 'Home',
            'recipient_name' => 'Kavindu Senanayake',
            'phone' => '0771234567',
            'address_line_1' => 'No 10, Flower Road',
            'city' => 'Colombo 07',
            'district' => 'Colombo',
            'is_default' => true,
        ]);

        // Second address created with is_default = true
        $secondData = [
            'label' => 'Office',
            'recipient_name' => 'Kavindu Office',
            'phone' => '0779998888',
            'address_line_1' => 'World Trade Center, West Tower',
            'city' => 'Colombo 01',
            'district' => 'Colombo',
            'postal_code' => '00100',
            'is_default' => true,
        ];

        $response = $this->actingAs($this->customer)->post(route('customer.addresses.store'), $secondData);
        $response->assertRedirect(route('customer.addresses.index'));

        // First address must now be demoted
        $this->assertFalse($firstAddress->fresh()->is_default);

        // Second address must be default
        $this->assertDatabaseHas('customer_addresses', [
            'user_id' => $this->customer->id,
            'label' => 'Office',
            'is_default' => true,
        ]);
    }

    public function test_customer_can_update_existing_address(): void
    {
        $address = CustomerAddress::create([
            'user_id' => $this->customer->id,
            'label' => 'Home',
            'recipient_name' => 'Kavindu S.',
            'phone' => '0771234567',
            'address_line_1' => 'Old Address 123',
            'city' => 'Kandy',
            'district' => 'Kandy',
            'is_default' => true,
        ]);

        $updateData = [
            'label' => 'Parents Home',
            'recipient_name' => 'Kavindu Senanayake',
            'phone' => '0775554444',
            'address_line_1' => 'New Road 456',
            'city' => 'Peradeniya',
            'district' => 'Kandy',
            'postal_code' => '20400',
            'is_default' => true,
        ];

        $response = $this->actingAs($this->customer)->put(route('customer.addresses.update', $address->id), $updateData);

        $response->assertRedirect(route('customer.addresses.index'));
        $this->assertDatabaseHas('customer_addresses', [
            'id' => $address->id,
            'label' => 'Parents Home',
            'city' => 'Peradeniya',
            'address_line_1' => 'New Road 456',
        ]);
    }

    public function test_customer_can_delete_address_and_deleting_default_promotes_next_address(): void
    {
        $addr1 = CustomerAddress::create([
            'user_id' => $this->customer->id,
            'label' => 'Home 1',
            'recipient_name' => 'Kavindu',
            'phone' => '0771234567',
            'address_line_1' => 'Road 1',
            'city' => 'Colombo',
            'district' => 'Colombo',
            'is_default' => true,
        ]);

        $addr2 = CustomerAddress::create([
            'user_id' => $this->customer->id,
            'label' => 'Home 2',
            'recipient_name' => 'Kavindu',
            'phone' => '0771234567',
            'address_line_1' => 'Road 2',
            'city' => 'Galle',
            'district' => 'Galle',
            'is_default' => false,
        ]);

        $response = $this->actingAs($this->customer)->delete(route('customer.addresses.destroy', $addr1->id));

        $response->assertRedirect(route('customer.addresses.index'));
        $this->assertDatabaseMissing('customer_addresses', ['id' => $addr1->id]);

        // addr2 must be automatically promoted to default!
        $this->assertTrue($addr2->fresh()->is_default);
    }

    public function test_customer_can_set_default_address_via_dedicated_endpoint(): void
    {
        $addr1 = CustomerAddress::create([
            'user_id' => $this->customer->id,
            'label' => 'Home 1',
            'recipient_name' => 'Kavindu',
            'phone' => '0771234567',
            'address_line_1' => 'Road 1',
            'city' => 'Colombo',
            'district' => 'Colombo',
            'is_default' => true,
        ]);

        $addr2 = CustomerAddress::create([
            'user_id' => $this->customer->id,
            'label' => 'Home 2',
            'recipient_name' => 'Kavindu',
            'phone' => '0771234567',
            'address_line_1' => 'Road 2',
            'city' => 'Galle',
            'district' => 'Galle',
            'is_default' => false,
        ]);

        $response = $this->actingAs($this->customer)->post(route('customer.addresses.set-default', $addr2->id));

        $response->assertRedirect(route('customer.addresses.index'));
        $this->assertFalse($addr1->fresh()->is_default);
        $this->assertTrue($addr2->fresh()->is_default);
    }

    public function test_customer_cannot_view_update_or_delete_another_customers_address(): void
    {
        $foreignAddress = CustomerAddress::create([
            'user_id' => $this->otherCustomer->id,
            'label' => 'Secret Home',
            'recipient_name' => 'Nadeesha',
            'phone' => '0719876543',
            'address_line_1' => 'Secret Lane',
            'city' => 'Matara',
            'district' => 'Matara',
            'is_default' => true,
        ]);

        // 1. Attempt to update foreign address
        $updateResponse = $this->actingAs($this->customer)->put(route('customer.addresses.update', $foreignAddress->id), [
            'label' => 'Hacked',
            'recipient_name' => 'Hacker',
            'phone' => '0770000000',
            'address_line_1' => 'Hacked Street',
            'city' => 'Colombo',
            'district' => 'Colombo',
        ]);
        $updateResponse->assertStatus(404);

        // 2. Attempt to delete foreign address
        $deleteResponse = $this->actingAs($this->customer)->delete(route('customer.addresses.destroy', $foreignAddress->id));
        $deleteResponse->assertStatus(404);

        // 3. Attempt to set default on foreign address
        $defaultResponse = $this->actingAs($this->customer)->post(route('customer.addresses.set-default', $foreignAddress->id));
        $defaultResponse->assertStatus(404);

        // Verify foreign address was completely untouched
        $this->assertEquals('Secret Home', $foreignAddress->fresh()->label);
    }

    public function test_maximum_10_saved_addresses_limit_is_strictly_enforced(): void
    {
        // Seed 10 addresses for this customer
        for ($i = 1; $i <= 10; $i++) {
            CustomerAddress::create([
                'user_id' => $this->customer->id,
                'label' => "Location {$i}",
                'recipient_name' => 'Kavindu',
                'phone' => '0771234567',
                'address_line_1' => "Address Street {$i}",
                'city' => 'Colombo',
                'district' => 'Colombo',
                'is_default' => ($i === 1),
            ]);
        }

        $this->assertEquals(10, $this->customer->customerAddresses()->count());

        // Attempt to create the 11th address
        $eleventhAddressData = [
            'label' => 'Location 11',
            'recipient_name' => 'Kavindu',
            'phone' => '0771234567',
            'address_line_1' => 'Address Street 11',
            'city' => 'Colombo',
            'district' => 'Colombo',
        ];

        $response = $this->actingAs($this->customer)->post(route('customer.addresses.store'), $eleventhAddressData);

        $response->assertSessionHasErrors('address_limit');
        $this->assertEquals(10, $this->customer->customerAddresses()->count());
    }

    public function test_invalid_district_is_rejected(): void
    {
        $invalidData = [
            'label' => 'Home',
            'recipient_name' => 'Kavindu',
            'phone' => '0771234567',
            'address_line_1' => 'Main Road',
            'city' => 'City',
            'district' => 'NonExistentDistrict', // Invalid!
        ];

        $response = $this->actingAs($this->customer)->post(route('customer.addresses.store'), $invalidData);
        $response->assertSessionHasErrors('district');
    }

    public function test_editing_address_does_not_mutate_historical_order_shipping_address(): void
    {
        $address = CustomerAddress::create([
            'user_id' => $this->customer->id,
            'label' => 'Home',
            'recipient_name' => 'Kavindu Original',
            'phone' => '0771112222',
            'address_line_1' => 'Historical Address 100',
            'city' => 'Negombo',
            'district' => 'Gampaha',
            'postal_code' => '11500',
            'is_default' => true,
        ]);

        // Place an order with this address snapshot
        $order = Order::create([
            'order_number' => 'ORD-TEST-HISTORICAL-01',
            'customer_id' => $this->customer->id,
            'customer_name' => $address->recipient_name,
            'customer_phone' => $address->phone,
            'shipping_address' => $address->address_line_1,
            'district' => $address->district,
            'city' => $address->city,
            'postal_code' => $address->postal_code,
            'subtotal' => 2000.00,
            'delivery_charge' => 350.00,
            'total_amount' => 2350.00,
            'payment_method' => 'cod',
            'status' => 'placed',
            'tracking_token' => Order::generateTrackingToken(),
        ]);

        // Customer edits their saved address to something completely different
        $this->actingAs($this->customer)->put(route('customer.addresses.update', $address->id), [
            'label' => 'New Relocated Home',
            'recipient_name' => 'Kavindu Relocated',
            'phone' => '0779990000',
            'address_line_1' => 'Brand New Road 999',
            'city' => 'Jaffna',
            'district' => 'Jaffna',
            'postal_code' => '40000',
            'is_default' => true,
        ]);

        // The historical order MUST retain its exact original address snapshot!
        $freshOrder = $order->fresh();
        $this->assertEquals('Kavindu Original', $freshOrder->customer_name);
        $this->assertEquals('0771112222', $freshOrder->customer_phone);
        $this->assertEquals('Historical Address 100', $freshOrder->shipping_address);
        $this->assertEquals('Gampaha', $freshOrder->district);
        $this->assertEquals('Negombo', $freshOrder->city);
        $this->assertEquals('11500', $freshOrder->postal_code);
    }

    public function test_deleting_address_does_not_affect_historical_order(): void
    {
        $address = CustomerAddress::create([
            'user_id' => $this->customer->id,
            'label' => 'Home To Delete',
            'recipient_name' => 'Kavindu',
            'phone' => '0771112222',
            'address_line_1' => 'Temporary Road',
            'city' => 'Matale',
            'district' => 'Matale',
            'is_default' => true,
        ]);

        $order = Order::create([
            'order_number' => 'ORD-TEST-HISTORICAL-02',
            'customer_id' => $this->customer->id,
            'customer_name' => 'Kavindu',
            'customer_phone' => '0771112222',
            'shipping_address' => 'Temporary Road',
            'district' => 'Matale',
            'city' => 'Matale',
            'subtotal' => 1500.00,
            'delivery_charge' => 350.00,
            'total_amount' => 1850.00,
            'payment_method' => 'cod',
            'status' => 'delivered',
            'tracking_token' => Order::generateTrackingToken(),
        ]);

        $this->actingAs($this->customer)->delete(route('customer.addresses.destroy', $address->id));

        $this->assertDatabaseMissing('customer_addresses', ['id' => $address->id]);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'shipping_address' => 'Temporary Road']);
    }

    public function test_checkout_prefills_default_saved_address_for_authenticated_customer(): void
    {
        CustomerAddress::create([
            'user_id' => $this->customer->id,
            'label' => 'Home',
            'recipient_name' => 'Kavindu Default',
            'phone' => '0778887777',
            'address_line_1' => 'Default Lane 1',
            'city' => 'Colombo 05',
            'district' => 'Colombo',
            'is_default' => true,
        ]);

        $product = Product::create([
            'name_en' => 'Organic Serum',
            'name_si' => 'කාබනික සීරම්',
            'slug' => 'organic-serum-address-test',
            'sku' => 'AL-SER-999',
            'price' => 3500.00,
            'stock_quantity' => 10,
            'is_active' => true,
        ]);

        session(['cart' => [$product->id => 1]]);

        $response = $this->actingAs($this->customer)->get(route('checkout.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->component('Checkout/Index')
                ->has('savedAddresses', 1)
                ->where('savedAddresses.0.recipient_name', 'Kavindu Default')
        );
    }

    public function test_checkout_saves_address_to_book_when_save_checkbox_is_checked(): void
    {
        $product = Product::create([
            'name_en' => 'Day Cream',
            'name_si' => 'දිවා ක්‍රීම්',
            'slug' => 'day-cream-address-test',
            'sku' => 'AL-CRM-999',
            'price' => 2500.00,
            'stock_quantity' => 10,
            'is_active' => true,
        ]);

        session(['cart' => [$product->id => 1]]);

        $checkoutData = [
            'customer_name' => 'Kavindu Newly Saved',
            'customer_phone' => '0773334444',
            'customer_email' => 'kavindu@example.com',
            'shipping_address' => 'Brand New House No 77',
            'district' => 'Kalutara',
            'city' => 'Panadura',
            'postal_code' => '12500',
            'notes' => 'Call before arrival',
            'save_address' => true, // Checkbox checked!
        ];

        $response = $this->actingAs($this->customer)->post(route('checkout.store'), $checkoutData);

        // Order placed
        $this->assertDatabaseHas('orders', [
            'customer_name' => 'Kavindu Newly Saved',
            'shipping_address' => 'Brand New House No 77',
        ]);

        // Address automatically saved to CustomerAddress table
        $this->assertDatabaseHas('customer_addresses', [
            'user_id' => $this->customer->id,
            'recipient_name' => 'Kavindu Newly Saved',
            'phone' => '0773334444',
            'address_line_1' => 'Brand New House No 77',
            'city' => 'Panadura',
            'district' => 'Kalutara',
        ]);
    }

    public function test_management_staff_and_agents_are_redirected_away_from_customer_addresses(): void
    {
        // 1. Owner redirected to admin dashboard
        $ownerResponse = $this->actingAs($this->owner)->get(route('customer.addresses.index'));
        $ownerResponse->assertRedirect(route('admin.dashboard'));

        // 2. Agent redirected to agent dashboard
        $agentResponse = $this->actingAs($this->agent)->get(route('customer.addresses.index'));
        $agentResponse->assertRedirect(route('agent.dashboard'));
    }

    public function test_unchecking_current_default_address_automatically_promotes_another_address_to_default(): void
    {
        $addr1 = CustomerAddress::create([
            'user_id' => $this->customer->id,
            'label' => 'Primary Home',
            'recipient_name' => 'Kavindu',
            'phone' => '0771234567',
            'address_line_1' => 'Road 1',
            'city' => 'Colombo',
            'district' => 'Colombo',
            'is_default' => true,
        ]);

        // Sleep 1 second to guarantee distinct updated_at timestamps
        sleep(1);

        $addr2 = CustomerAddress::create([
            'user_id' => $this->customer->id,
            'label' => 'Secondary Office',
            'recipient_name' => 'Kavindu',
            'phone' => '0779998888',
            'address_line_1' => 'Road 2',
            'city' => 'Kandy',
            'district' => 'Kandy',
            'is_default' => false,
        ]);

        // Update addr1 unchecking default
        $response = $this->actingAs($this->customer)->put(route('customer.addresses.update', $addr1->id), [
            'label' => 'Primary Home Updated',
            'recipient_name' => 'Kavindu',
            'phone' => '0771234567',
            'address_line_1' => 'Road 1 Updated',
            'city' => 'Colombo',
            'district' => 'Colombo',
            'is_default' => false, // Attempt to uncheck default!
        ]);

        $response->assertRedirect(route('customer.addresses.index'));

        // addr1 is demoted
        $this->assertFalse($addr1->fresh()->is_default);

        // addr2 must be promoted to default, ensuring exactly 1 default exists!
        $this->assertTrue($addr2->fresh()->is_default);

        // Confirm exactly 1 default exists across all addresses
        $this->assertEquals(1, $this->customer->customerAddresses()->where('is_default', true)->count());
    }

    public function test_unchecking_current_default_when_only_one_address_exists_keeps_it_as_default(): void
    {
        $soleAddress = CustomerAddress::create([
            'user_id' => $this->customer->id,
            'label' => 'Only Home',
            'recipient_name' => 'Kavindu',
            'phone' => '0771234567',
            'address_line_1' => 'Sole Lane 1',
            'city' => 'Galle',
            'district' => 'Galle',
            'is_default' => true,
        ]);

        $response = $this->actingAs($this->customer)->put(route('customer.addresses.update', $soleAddress->id), [
            'label' => 'Only Home Renamed',
            'recipient_name' => 'Kavindu',
            'phone' => '0771234567',
            'address_line_1' => 'Sole Lane 1',
            'city' => 'Galle',
            'district' => 'Galle',
            'is_default' => false, // Cannot leave 0 defaults when addresses exist!
        ]);

        $response->assertRedirect(route('customer.addresses.index'));
        $this->assertTrue($soleAddress->fresh()->is_default);
        $this->assertEquals(1, $this->customer->customerAddresses()->where('is_default', true)->count());
    }

    public function test_service_directly_enforces_10_address_limit_inside_transaction(): void
    {
        for ($i = 1; $i <= 10; $i++) {
            CustomerAddress::create([
                'user_id' => $this->customer->id,
                'label' => "Location {$i}",
                'recipient_name' => 'Kavindu',
                'phone' => '0771234567',
                'address_line_1' => "Address Street {$i}",
                'city' => 'Colombo',
                'district' => 'Colombo',
                'is_default' => ($i === 1),
            ]);
        }

        $service = app(\App\Services\CustomerAddressService::class);

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        $service->createAddress($this->customer, [
            'label' => 'Location 11',
            'recipient_name' => 'Kavindu',
            'phone' => '0771234567',
            'address_line_1' => 'Address Street 11',
            'city' => 'Colombo',
            'district' => 'Colombo',
        ]);
    }

    public function test_checkout_skips_saving_address_when_customer_already_has_10_addresses(): void
    {
        for ($i = 1; $i <= 10; $i++) {
            CustomerAddress::create([
                'user_id' => $this->customer->id,
                'label' => "Location {$i}",
                'recipient_name' => 'Kavindu',
                'phone' => '0771234567',
                'address_line_1' => "Address Street {$i}",
                'city' => 'Colombo',
                'district' => 'Colombo',
                'is_default' => ($i === 1),
            ]);
        }

        $product = Product::create([
            'name_en' => 'Lip Balm',
            'name_si' => 'තොල් ආලේපන',
            'slug' => 'lip-balm-cap-test',
            'sku' => 'AL-LIP-999',
            'price' => 1200.00,
            'stock_quantity' => 10,
            'is_active' => true,
        ]);

        session(['cart' => [$product->id => 1]]);

        $checkoutData = [
            'customer_name' => 'Kavindu At Cap',
            'customer_phone' => '0779991111',
            'customer_email' => 'kavindu@example.com',
            'shipping_address' => 'Overflow Street 99',
            'district' => 'Galle',
            'city' => 'Hikkaduwa',
            'postal_code' => '80240',
            'save_address' => true,
        ];

        $response = $this->actingAs($this->customer)->post(route('checkout.store'), $checkoutData);

        // Order succeeds!
        $this->assertDatabaseHas('orders', [
            'customer_name' => 'Kavindu At Cap',
            'shipping_address' => 'Overflow Street 99',
        ]);

        // Address count remains exactly 10, never exceeding 10!
        $this->assertEquals(10, $this->customer->customerAddresses()->count());
    }

    public function test_duplicate_checkout_address_is_not_re_added_to_book(): void
    {
        CustomerAddress::create([
            'user_id' => $this->customer->id,
            'label' => 'Existing Home',
            'recipient_name' => 'Kavindu',
            'phone' => '0771234567',
            'address_line_1' => 'Identical Street 50',
            'city' => 'Colombo 03',
            'district' => 'Colombo',
            'is_default' => true,
        ]);

        $product = Product::create([
            'name_en' => 'Face Scrub',
            'name_si' => 'මුහුණු ස්ක්‍රබ්',
            'slug' => 'face-scrub-duplicate-test',
            'sku' => 'AL-SCR-999',
            'price' => 1800.00,
            'stock_quantity' => 10,
            'is_active' => true,
        ]);

        session(['cart' => [$product->id => 1]]);

        $checkoutData = [
            'customer_name' => 'Kavindu',
            'customer_phone' => '0771234567',
            'customer_email' => 'kavindu@example.com',
            'shipping_address' => 'Identical Street 50',
            'district' => 'Colombo',
            'city' => 'Colombo 03',
            'save_address' => true,
        ];

        $response = $this->actingAs($this->customer)->post(route('checkout.store'), $checkoutData);

        // Order succeeds
        $this->assertDatabaseHas('orders', ['customer_name' => 'Kavindu']);

        // Address count remains exactly 1 (duplicate skipped)
        $this->assertEquals(1, $this->customer->customerAddresses()->count());
    }
}

