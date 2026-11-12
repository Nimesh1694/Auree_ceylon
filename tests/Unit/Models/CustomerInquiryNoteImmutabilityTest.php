<?php

namespace Tests\Unit\Models;

use App\Models\CustomerInquiry;
use App\Models\CustomerInquiryNote;
use App\Models\User;
use App\Policies\CustomerInquiryPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class CustomerInquiryNoteImmutabilityTest extends TestCase
{
    use RefreshDatabase;

    protected CustomerInquiry $inquiry;
    protected User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->inquiry = CustomerInquiry::create([
            'inquiry_number' => 'INQ-20261111-TEST1',
            'name' => 'Dilshan Ranasinghe',
            'email' => 'customer@example.com',
            'phone' => '+94771234567',
            'subject' => 'Product Delivery Delay',
            'message' => 'My ordered parcel is running late.',
            'status' => CustomerInquiry::STATUS_NEW,
            'priority' => CustomerInquiry::PRIORITY_NORMAL,
        ]);

        $this->staff = User::factory()->create([
            'role' => 'operations_manager',
            'status' => 'active',
        ]);
    }

    /**
     * 1. Test updating a customer inquiry note throws LogicException.
     */
    public function test_updating_a_customer_inquiry_note_throws_logic_exception(): void
    {
        $note = CustomerInquiryNote::create([
            'customer_inquiry_id' => $this->inquiry->id,
            'user_id' => $this->staff->id,
            'note' => 'Initial internal observation by support staff.',
        ]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Customer inquiry notes are immutable and cannot be modified.');

        $note->update(['note' => 'Tampered note content']);
    }

    /**
     * 2. Test deleting a customer inquiry note throws LogicException.
     */
    public function test_deleting_a_customer_inquiry_note_throws_logic_exception(): void
    {
        $note = CustomerInquiryNote::create([
            'customer_inquiry_id' => $this->inquiry->id,
            'user_id' => $this->staff->id,
            'note' => 'Internal note that must never disappear.',
        ]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Customer inquiry notes are immutable compliance records and cannot be deleted.');

        $note->delete();
    }

    /**
     * 3. Test parent customer inquiry deletion throws LogicException at model level.
     */
    public function test_parent_customer_inquiry_deletion_throws_logic_exception_at_model_level(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Customer inquiries are immutable compliance records and cannot be deleted.');

        $this->inquiry->delete();
    }

    /**
     * 4. Test policy unconditionally denies inquiry deletion.
     */
    public function test_policy_unconditionally_denies_inquiry_deletion(): void
    {
        $policy = new CustomerInquiryPolicy();

        // Even Owner cannot delete inquiry compliance records
        $owner = User::factory()->create(['role' => 'owner', 'status' => 'active']);
        $this->assertFalse($policy->delete($owner, $this->inquiry));

        // Super Admin cannot delete
        $superAdmin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);
        $this->assertFalse($policy->delete($superAdmin, $this->inquiry));

        // Staff cannot delete
        $this->assertFalse($policy->delete($this->staff, $this->inquiry));
    }
}

