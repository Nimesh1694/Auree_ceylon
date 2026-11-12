<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Jobs\SendCustomerInquiryEmailJob;
use App\Models\CustomerInquiry;
use App\Models\CustomerInquiryNote;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ContactInquiryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        Queue::fake([SendCustomerInquiryEmailJob::class]);
    }

    public function test_public_contact_page_renders_successfully(): void
    {
        $response = $this->get(route('pages.contact'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Pages/Contact')
            ->where('prefill', null)
        );
    }

    public function test_public_contact_page_prefills_authenticated_customer_data(): void
    {
        $customer = User::factory()->create([
            'role' => UserRole::CUSTOMER,
            'name' => 'Sophia Montgomery',
            'email' => 'sophia@example.com',
            'phone' => '+94771234567',
        ]);

        $response = $this->actingAs($customer)->get(route('pages.contact'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Pages/Contact')
            ->where('prefill.name', 'Sophia Montgomery')
            ->where('prefill.email', 'sophia@example.com')
            ->where('prefill.phone', '+94771234567')
        );
    }

    public function test_guest_can_submit_valid_contact_inquiry(): void
    {
        $payload = [
            'name' => 'Elena Rostova',
            'email' => 'elena.rostova@example.com',
            'phone' => '+94779876543',
            'subject' => 'Product Formulation Inquiry',
            'message' => 'I would like to inquire whether the Botanical Glow Serum contains niacinamide or essential oils.',
        ];

        $response = $this->post(route('pages.contact.submit'), $payload);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $response->assertSessionHas('inquiry_number');

        $this->assertDatabaseCount('customer_inquiries', 1);

        $inquiry = CustomerInquiry::first();
        $this->assertNotNull($inquiry);
        $this->assertNull($inquiry->customer_id);
        $this->assertEquals('Elena Rostova', $inquiry->name);
        $this->assertEquals('elena.rostova@example.com', $inquiry->email);
        $this->assertEquals('+94779876543', $inquiry->phone);
        $this->assertEquals('Product Formulation Inquiry', $inquiry->subject);
        $this->assertEquals($payload['message'], $inquiry->message);
        $this->assertEquals(CustomerInquiry::STATUS_NEW, $inquiry->status);
        $this->assertEquals(CustomerInquiry::PRIORITY_NORMAL, $inquiry->priority);
        $this->assertEquals(CustomerInquiry::NOTIFICATION_PENDING, $inquiry->notification_status);
        $this->assertMatchesRegularExpression('/^INQ-\d{8}-[A-Z0-9]{5}$/', $inquiry->inquiry_number);
    }

    public function test_authenticated_customer_inquiry_associates_customer_id(): void
    {
        $customer = User::factory()->create([
            'role' => UserRole::CUSTOMER,
            'name' => 'Claire Dupont',
            'email' => 'claire@example.com',
        ]);

        $payload = [
            'name' => 'Claire Dupont',
            'email' => 'claire@example.com',
            'subject' => 'Order Delivery Tracking Inquiry',
            'message' => 'Could you please check on the estimated dispatch date for my recent purchase?',
        ];

        $response = $this->actingAs($customer)->post(route('pages.contact.submit'), $payload);

        $response->assertRedirect();
        $this->assertDatabaseCount('customer_inquiries', 1);

        $inquiry = CustomerInquiry::first();
        $this->assertEquals($customer->id, $inquiry->customer_id);
        $this->assertEquals('claire@example.com', $inquiry->email);
    }

    public function test_contact_submission_validates_required_fields(): void
    {
        $response = $this->post(route('pages.contact.submit'), []);

        $response->assertRedirect();
        $response->assertSessionHasErrors(['name', 'email', 'subject', 'message']);
        $this->assertDatabaseCount('customer_inquiries', 0);
    }

    public function test_contact_submission_rejects_invalid_email_format(): void
    {
        $payload = [
            'name' => 'Test User',
            'email' => 'not-a-valid-email-address',
            'subject' => 'Valid Subject Line',
            'message' => 'This is a valid message that is longer than ten characters.',
        ];

        $response = $this->post(route('pages.contact.submit'), $payload);

        $response->assertRedirect();
        $response->assertSessionHasErrors(['email']);
        $this->assertDatabaseCount('customer_inquiries', 0);
    }

    public function test_contact_submission_rejects_short_message_under_ten_characters(): void
    {
        $payload = [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'subject' => 'Valid Subject',
            'message' => 'Too short',
        ];

        $response = $this->post(route('pages.contact.submit'), $payload);

        $response->assertRedirect();
        $response->assertSessionHasErrors(['message']);
        $this->assertDatabaseCount('customer_inquiries', 0);
    }

    public function test_contact_submission_rejects_excessively_long_message(): void
    {
        $payload = [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'subject' => 'Valid Subject',
            'message' => str_repeat('A', 3001),
        ];

        $response = $this->post(route('pages.contact.submit'), $payload);

        $response->assertRedirect();
        $response->assertSessionHasErrors(['message']);
        $this->assertDatabaseCount('customer_inquiries', 0);
    }

    public function test_honeypot_website_silently_intercepts_bot_spam_without_persistence(): void
    {
        $payload = [
            'name' => 'Spam Bot',
            'email' => 'bot@spammer.org',
            'subject' => 'Cheap SEO Services',
            'message' => 'Buy backlinks now for cheap prices and high search rankings guaranteed.',
            'website' => 'http://spamlinks-network.biz',
        ];

        $response = $this->post(route('pages.contact.submit'), $payload);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertDatabaseCount('customer_inquiries', 0);
    }

    public function test_honeypot_fax_number_silently_intercepts_bot_spam(): void
    {
        $payload = [
            'name' => 'Spam Bot 2',
            'email' => 'bot2@spammer.org',
            'subject' => 'Financial Scam Offer',
            'message' => 'Click here for immediate wire transfer approval and high interest rates.',
            'fax_number' => '+18005550199',
        ];

        $response = $this->post(route('pages.contact.submit'), $payload);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertDatabaseCount('customer_inquiries', 0);
    }

    public function test_contact_form_is_rate_limited_to_five_requests_per_minute(): void
    {
        $payload = [
            'name' => 'Rapid Submitter',
            'email' => 'rapid@example.com',
            'subject' => 'Rapid Inquiry',
            'message' => 'This is a message sent multiple times to test rate limiting behavior.',
        ];

        for ($i = 0; $i < 5; $i++) {
            $response = $this->post(route('pages.contact.submit'), $payload);
            $response->assertRedirect();
        }

        // The 6th request within the same minute should be rate limited (429 HTTP status)
        $response = $this->post(route('pages.contact.submit'), $payload);
        $response->assertStatus(429);
    }

    public function test_inquiry_generates_unique_inquiry_number_matching_format(): void
    {
        $number1 = CustomerInquiry::generateInquiryNumber();
        $number2 = CustomerInquiry::generateInquiryNumber();

        $this->assertMatchesRegularExpression('/^INQ-\d{8}-[A-Z0-9]{5}$/', $number1);
        $this->assertMatchesRegularExpression('/^INQ-\d{8}-[A-Z0-9]{5}$/', $number2);
        $this->assertNotEquals($number1, $number2);
    }

    public function test_inquiry_notes_relationship_operates_append_only(): void
    {
        $inquiry = CustomerInquiry::create([
            'inquiry_number' => CustomerInquiry::generateInquiryNumber(),
            'name' => 'Client Note Test',
            'email' => 'note.test@example.com',
            'subject' => 'Test Subject',
            'message' => 'Test message for internal administrative notes appending.',
        ]);

        $staffUser = User::factory()->create(['role' => UserRole::OPERATIONS_MANAGER]);

        $note1 = $inquiry->notes()->create([
            'user_id' => $staffUser->id,
            'note' => 'First note: Reviewed customer message and assigned to formulation specialist.',
        ]);

        $note2 = $inquiry->notes()->create([
            'user_id' => $staffUser->id,
            'note' => 'Second note: Specialist responded with formulation details.',
        ]);

        $this->assertDatabaseCount('customer_inquiry_notes', 2);
        $this->assertCount(2, $inquiry->fresh()->notes);

        // Check relationship back to inquiry and author
        $this->assertEquals($inquiry->id, $note1->inquiry->id);
        $this->assertEquals($staffUser->id, $note1->author->id);
    }

    public function test_inquiry_notes_cascade_delete_with_parent_inquiry(): void
    {
        $inquiry = CustomerInquiry::create([
            'inquiry_number' => CustomerInquiry::generateInquiryNumber(),
            'name' => 'Cascade Delete Test',
            'email' => 'cascade@example.com',
            'subject' => 'Cascade Test',
            'message' => 'Testing cascade deletion on parent inquiry removal.',
        ]);

        $staffUser = User::factory()->create(['role' => UserRole::OPERATIONS_MANAGER]);

        $inquiry->notes()->create([
            'user_id' => $staffUser->id,
            'note' => 'Internal note that should cascade delete.',
        ]);

        $this->assertDatabaseCount('customer_inquiry_notes', 1);

        // Under Option A, Eloquent deletion is blocked; database cascade is verified via direct query
        \Illuminate\Support\Facades\DB::table('customer_inquiries')->where('id', $inquiry->id)->delete();

        $this->assertDatabaseCount('customer_inquiries', 0);
        $this->assertDatabaseCount('customer_inquiry_notes', 0);
    }

    public function test_inquiry_notes_preserve_author_attribution_null_on_user_deletion(): void
    {
        $inquiry = CustomerInquiry::create([
            'inquiry_number' => CustomerInquiry::generateInquiryNumber(),
            'name' => 'Null On Delete Test',
            'email' => 'nullondelete@example.com',
            'subject' => 'Null On Delete Test',
            'message' => 'Testing user nullOnDelete behavior for audit note retention.',
        ]);

        $staffUser = User::factory()->create(['role' => UserRole::OPERATIONS_MANAGER]);

        $note = $inquiry->notes()->create([
            'user_id' => $staffUser->id,
            'note' => 'Staff note that should retain content even if author account is deleted.',
        ]);

        $staffUser->delete();

        $this->assertDatabaseCount('customer_inquiry_notes', 1);
        $this->assertNull($note->fresh()->user_id);
        $this->assertEquals('Staff note that should retain content even if author account is deleted.', $note->fresh()->note);
    }

    public function test_inquiry_scopes_filter_by_status_and_priority(): void
    {
        CustomerInquiry::create([
            'inquiry_number' => CustomerInquiry::generateInquiryNumber(),
            'name' => 'New Urgent',
            'email' => 'urgent@example.com',
            'subject' => 'Urgent Item',
            'message' => 'High priority inquiry requiring immediate attention.',
            'status' => CustomerInquiry::STATUS_NEW,
            'priority' => CustomerInquiry::PRIORITY_URGENT,
        ]);

        CustomerInquiry::create([
            'inquiry_number' => CustomerInquiry::generateInquiryNumber(),
            'name' => 'Resolved Low',
            'email' => 'resolved@example.com',
            'subject' => 'Resolved Item',
            'message' => 'Standard priority inquiry that was resolved.',
            'status' => CustomerInquiry::STATUS_RESOLVED,
            'priority' => CustomerInquiry::PRIORITY_LOW,
        ]);

        $this->assertEquals(1, CustomerInquiry::status(CustomerInquiry::STATUS_NEW)->count());
        $this->assertEquals(1, CustomerInquiry::status(CustomerInquiry::STATUS_RESOLVED)->count());
        $this->assertEquals(1, CustomerInquiry::priority(CustomerInquiry::PRIORITY_URGENT)->count());
        $this->assertEquals(1, CustomerInquiry::priority(CustomerInquiry::PRIORITY_LOW)->count());
        $this->assertEquals(1, CustomerInquiry::open()->count());
        $this->assertEquals(1, CustomerInquiry::resolved()->count());
    }
}
