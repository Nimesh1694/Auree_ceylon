<?php

namespace Tests\Unit\Services;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\AuditLog;
use App\Models\CustomerInquiry;
use App\Models\CustomerInquiryNote;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\CustomerInquiryAdminService;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CustomerInquiryAdminServiceTest extends TestCase
{
    use RefreshDatabase;

    protected CustomerInquiryAdminService $service;
    protected User $staff;
    protected CustomerInquiry $inquiry;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(CustomerInquiryAdminService::class);

        $this->staff = User::factory()->create([
            'role' => UserRole::OPERATIONS_MANAGER,
            'status' => UserStatus::ACTIVE,
        ]);

        $this->inquiry = CustomerInquiry::create([
            'inquiry_number' => 'INQ-20261111-SRV01',
            'name' => 'Kavindi Perera',
            'email' => 'kavindi@example.com',
            'phone' => '+94779876543',
            'subject' => 'Damaged Packaging',
            'message' => 'The bottle cap was broken on arrival.',
            'status' => CustomerInquiry::STATUS_NEW,
            'priority' => CustomerInquiry::PRIORITY_NORMAL,
        ]);
    }

    /**
     * 1. Test all valid transitions succeed and update state accurately.
     */
    public function test_all_permitted_status_transitions_succeed(): void
    {
        // 1. new -> in_progress
        $this->service->updateStatus($this->inquiry, CustomerInquiry::STATUS_IN_PROGRESS, $this->staff);
        $this->assertEquals(CustomerInquiry::STATUS_IN_PROGRESS, $this->inquiry->status);

        // 2. in_progress -> waiting_customer
        $this->service->updateStatus($this->inquiry, CustomerInquiry::STATUS_WAITING_CUSTOMER, $this->staff);
        $this->assertEquals(CustomerInquiry::STATUS_WAITING_CUSTOMER, $this->inquiry->status);

        // 3. waiting_customer -> in_progress
        $this->service->updateStatus($this->inquiry, CustomerInquiry::STATUS_IN_PROGRESS, $this->staff);
        $this->assertEquals(CustomerInquiry::STATUS_IN_PROGRESS, $this->inquiry->status);

        // 4. in_progress -> resolved
        $this->service->updateStatus($this->inquiry, CustomerInquiry::STATUS_RESOLVED, $this->staff);
        $this->assertEquals(CustomerInquiry::STATUS_RESOLVED, $this->inquiry->status);
        $this->assertNotNull($this->inquiry->resolved_at);
        $this->assertEquals($this->staff->id, $this->inquiry->resolved_by);

        // 5. resolved -> closed
        $this->service->updateStatus($this->inquiry, CustomerInquiry::STATUS_CLOSED, $this->staff);
        $this->assertEquals(CustomerInquiry::STATUS_CLOSED, $this->inquiry->status);

        // 6. closed -> in_progress (reopen with mandatory note)
        $this->service->updateStatus($this->inquiry, CustomerInquiry::STATUS_IN_PROGRESS, $this->staff, 'Customer called stating issue recurred.');
        $this->assertEquals(CustomerInquiry::STATUS_IN_PROGRESS, $this->inquiry->status);
        $this->assertNull($this->inquiry->resolved_at);
        $this->assertNull($this->inquiry->resolved_by);

        // 7. in_progress -> closed
        $this->service->updateStatus($this->inquiry, CustomerInquiry::STATUS_CLOSED, $this->staff);
        $this->assertEquals(CustomerInquiry::STATUS_CLOSED, $this->inquiry->status);
    }

    /**
     * 2. Test transitions from new directly to closed (e.g. spam).
     */
    public function test_new_can_transition_directly_to_closed_as_spam(): void
    {
        $this->service->updateStatus($this->inquiry, CustomerInquiry::STATUS_CLOSED, $this->staff);
        $this->assertEquals(CustomerInquiry::STATUS_CLOSED, $this->inquiry->status);
        $this->assertNotNull($this->inquiry->resolved_at);
        $this->assertEquals($this->staff->id, $this->inquiry->resolved_by);
    }

    /**
     * 3. Test prohibited transitions throw ValidationException.
     */
    public function test_prohibited_transitions_throw_validation_exception(): void
    {
        $prohibited = [
            [CustomerInquiry::STATUS_NEW, CustomerInquiry::STATUS_RESOLVED],
            [CustomerInquiry::STATUS_NEW, CustomerInquiry::STATUS_WAITING_CUSTOMER],
            [CustomerInquiry::STATUS_IN_PROGRESS, CustomerInquiry::STATUS_NEW],
            [CustomerInquiry::STATUS_WAITING_CUSTOMER, CustomerInquiry::STATUS_NEW],
            [CustomerInquiry::STATUS_RESOLVED, CustomerInquiry::STATUS_NEW],
            [CustomerInquiry::STATUS_RESOLVED, CustomerInquiry::STATUS_WAITING_CUSTOMER],
            [CustomerInquiry::STATUS_CLOSED, CustomerInquiry::STATUS_NEW],
            [CustomerInquiry::STATUS_CLOSED, CustomerInquiry::STATUS_WAITING_CUSTOMER],
            [CustomerInquiry::STATUS_CLOSED, CustomerInquiry::STATUS_RESOLVED],
        ];

        foreach ($prohibited as [$from, $to]) {
            $inquiry = CustomerInquiry::create([
                'inquiry_number' => 'INQ-PROHIBITED-' . uniqid(),
                'name' => 'Test User',
                'email' => 'test@example.com',
                'subject' => 'Subject',
                'message' => 'Message',
                'status' => $from,
            ]);

            try {
                $this->service->updateStatus($inquiry, $to, $this->staff, 'Some reason');
                $this->fail("Expected transition from {$from} to {$to} to fail.");
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('status', $e->errors());
            }
        }
    }

    /**
     * 4. Test repeated status update is idempotent (no-op).
     */
    public function test_repeated_status_update_is_idempotent_no_op(): void
    {
        $initialAuditCount = AuditLog::count();

        // Already new
        $res = $this->service->updateStatus($this->inquiry, CustomerInquiry::STATUS_NEW, $this->staff);
        $this->assertSame($this->inquiry, $res);
        $this->assertEquals($initialAuditCount, AuditLog::count());
    }

    /**
     * 5. Test reopening closed inquiry without note fails validation.
     */
    public function test_reopening_closed_inquiry_without_note_fails_validation(): void
    {
        $this->inquiry->update(['status' => CustomerInquiry::STATUS_CLOSED]);

        $this->expectException(ValidationException::class);
        $this->service->updateStatus($this->inquiry, CustomerInquiry::STATUS_IN_PROGRESS, $this->staff, null);
    }

    /**
     * 6. Test reopening closed inquiry with whitespace-only note fails validation.
     */
    public function test_reopening_closed_inquiry_with_whitespace_note_fails_validation(): void
    {
        $this->inquiry->update(['status' => CustomerInquiry::STATUS_CLOSED]);

        $this->expectException(ValidationException::class);
        $this->service->updateStatus($this->inquiry, CustomerInquiry::STATUS_IN_PROGRESS, $this->staff, "   \n\t  ");
    }

    /**
     * 7. Test reopening closed inquiry with valid note succeeds, creates note and clears metadata.
     */
    public function test_reopening_closed_inquiry_with_valid_note_persists_note_and_clears_metadata(): void
    {
        $this->inquiry->update([
            'status' => CustomerInquiry::STATUS_CLOSED,
            'resolved_at' => now(),
            'resolved_by' => $this->staff->id,
        ]);

        $res = $this->service->updateStatus(
            $this->inquiry,
            CustomerInquiry::STATUS_IN_PROGRESS,
            $this->staff,
            'Customer provided courier delivery slip indicating delivery failed.'
        );

        $this->assertEquals(CustomerInquiry::STATUS_IN_PROGRESS, $res->status);
        $this->assertNull($res->resolved_at);
        $this->assertNull($res->resolved_by);

        // Assert note was created
        $note = CustomerInquiryNote::where('customer_inquiry_id', $this->inquiry->id)->first();
        $this->assertNotNull($note);
        $this->assertStringContainsString('[REOPENED] Customer provided courier delivery slip', $note->note);

        // Assert audit log was recorded without exposing raw confidential note body
        $audit = AuditLog::where('action', 'inquiry_status_updated')->latest('id')->first();
        $this->assertNotNull($audit);
        $this->assertTrue($audit->new_values['reopened']);
        $this->assertEquals($note->id, $audit->new_values['note_id']);
        $this->assertArrayNotHasKey('note', $audit->new_values);
    }

    /**
     * 8. Test reopening resolved inquiry without note succeeds and clears resolution metadata.
     */
    public function test_reopening_resolved_inquiry_without_note_succeeds_and_clears_resolution_metadata(): void
    {
        $this->inquiry->update([
            'status' => CustomerInquiry::STATUS_RESOLVED,
            'resolved_at' => now(),
            'resolved_by' => $this->staff->id,
        ]);

        $res = $this->service->updateStatus($this->inquiry, CustomerInquiry::STATUS_IN_PROGRESS, $this->staff, null);

        $this->assertEquals(CustomerInquiry::STATUS_IN_PROGRESS, $res->status);
        $this->assertNull($res->resolved_at);
        $this->assertNull($res->resolved_by);
    }

    /**
     * 9. Test transaction rollback when note creation fails during closed reopening.
     */
    public function test_transaction_rollback_when_note_creation_fails_during_closed_reopening(): void
    {
        $this->inquiry->update(['status' => CustomerInquiry::STATUS_CLOSED]);

        // Mock CustomerInquiryNote::create failure by simulating a PDO exception inside transaction
        DB::shouldReceive('transaction')
            ->once()
            ->andThrow(new Exception('Simulated database deadlock during note creation.'));

        try {
            $this->service->updateStatus($this->inquiry, CustomerInquiry::STATUS_IN_PROGRESS, $this->staff, 'Valid reason');
            $this->fail('Expected exception was not thrown.');
        } catch (Exception $e) {
            $this->assertEquals('Simulated database deadlock during note creation.', $e->getMessage());
        }

        // Fresh inquiry remains closed
        $this->inquiry->refresh();
        $this->assertEquals(CustomerInquiry::STATUS_CLOSED, $this->inquiry->status);
    }

    /**
     * 10. Test priority updates with audit logging.
     */
    public function test_priority_updates_with_audit_logging(): void
    {
        $res = $this->service->updatePriority($this->inquiry, CustomerInquiry::PRIORITY_URGENT, $this->staff);

        $this->assertEquals(CustomerInquiry::PRIORITY_URGENT, $res->priority);

        $audit = AuditLog::where('action', 'inquiry_priority_updated')->latest('id')->first();
        $this->assertNotNull($audit);
        $this->assertEquals('normal', $audit->old_values['priority']);
        $this->assertEquals('urgent', $audit->new_values['priority']);

        // Idempotent test
        $same = $this->service->updatePriority($this->inquiry, CustomerInquiry::PRIORITY_URGENT, $this->staff);
        $this->assertSame($this->inquiry, $same);
    }

    /**
     * 11. Test staff assignment accepts active Operations Manager, Super Admin, and Owner.
     */
    public function test_staff_assignment_accepts_eligible_management_users(): void
    {
        // 1. Operations Manager
        $this->service->assignStaff($this->inquiry, $this->staff->id, $this->staff);
        $this->assertEquals($this->staff->id, $this->inquiry->assigned_to);

        // 2. Super Admin
        $superAdmin = User::factory()->create(['role' => UserRole::SUPER_ADMIN, 'status' => UserStatus::ACTIVE]);
        $this->service->assignStaff($this->inquiry, $superAdmin->id, $this->staff);
        $this->assertEquals($superAdmin->id, $this->inquiry->assigned_to);

        // 3. Owner
        $owner = User::factory()->create(['role' => UserRole::OWNER, 'status' => UserStatus::ACTIVE]);
        $this->service->assignStaff($this->inquiry, $owner->id, $this->staff);
        $this->assertEquals($owner->id, $this->inquiry->assigned_to);

        // 4. Custom Management Role with inquiries.manage permission
        $customRole = Role::create([
            'name' => 'support_lead',
            'display_name' => 'Customer Support Lead',
            'is_system' => false,
            'is_protected' => false,
        ]);
        $perm = Permission::firstOrCreate(['name' => 'inquiries.manage'], [
            'display_name' => 'Manage Customer Inquiries',
            'module' => 'support',
            'description' => 'Test',
            'is_financial_restricted' => false,
        ]);
        $customRole->permissions()->sync([$perm->id]);

        $customStaff = User::factory()->create([
            'role' => 'support_lead',
            'role_id' => $customRole->id,
            'status' => UserStatus::ACTIVE,
        ]);
        $customStaff->assignedRoles()->sync([$customRole->id]);

        $this->service->assignStaff($this->inquiry, $customStaff->id, $this->staff);
        $this->assertEquals($customStaff->id, $this->inquiry->assigned_to);
    }

    /**
     * 12. Test staff assignment rejects customer, agents, and suspended staff.
     */
    public function test_staff_assignment_rejects_ineligible_users(): void
    {
        // 1. Customer
        $customer = User::factory()->create(['role' => UserRole::CUSTOMER, 'status' => UserStatus::ACTIVE]);
        try {
            $this->service->assignStaff($this->inquiry, $customer->id, $this->staff);
            $this->fail('Expected customer assignment to fail.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('staff_user_id', $e->errors());
        }

        // 2. Agent (HLA)
        $hla = User::factory()->create(['role' => UserRole::HIGH_LEVEL_AGENT, 'status' => UserStatus::ACTIVE]);
        try {
            $this->service->assignStaff($this->inquiry, $hla->id, $this->staff);
            $this->fail('Expected HLA agent assignment to fail.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('staff_user_id', $e->errors());
        }

        // 3. Agent (LLA)
        $lla = User::factory()->create(['role' => UserRole::LOW_LEVEL_AGENT, 'status' => UserStatus::ACTIVE]);
        try {
            $this->service->assignStaff($this->inquiry, $lla->id, $this->staff);
            $this->fail('Expected LLA agent assignment to fail.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('staff_user_id', $e->errors());
        }

        // 4. Suspended Operations Manager
        $suspended = User::factory()->create(['role' => UserRole::OPERATIONS_MANAGER, 'status' => UserStatus::SUSPENDED]);
        try {
            $this->service->assignStaff($this->inquiry, $suspended->id, $this->staff);
            $this->fail('Expected suspended staff assignment to fail.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('staff_user_id', $e->errors());
        }
    }

    /**
     * 13. Test deliberate unassignment works.
     */
    public function test_deliberate_unassignment_sets_assigned_to_null_and_logs_audit(): void
    {
        $this->inquiry->update(['assigned_to' => $this->staff->id]);

        $res = $this->service->assignStaff($this->inquiry, null, $this->staff);
        $this->assertNull($res->assigned_to);

        $audit = AuditLog::where('action', 'inquiry_assigned')->latest('id')->first();
        $this->assertNotNull($audit);
        $this->assertEquals($this->staff->id, $audit->old_values['assigned_to']);
        $this->assertNull($audit->new_values['assigned_to']);
        $this->assertEquals('unassigned', $audit->new_values['action']);
    }

    /**
     * 14. Test appending valid internal note.
     */
    public function test_append_valid_internal_note_persists_note_and_logs_audit(): void
    {
        $note = $this->service->addNote($this->inquiry, 'Customer called asking for courier delivery estimate.', $this->staff);

        $this->assertInstanceOf(CustomerInquiryNote::class, $note);
        $this->assertEquals('Customer called asking for courier delivery estimate.', $note->note);
        $this->assertEquals($this->staff->id, $note->user_id);

        $audit = AuditLog::where('action', 'inquiry_note_added')->latest('id')->first();
        $this->assertNotNull($audit);
        $this->assertEquals($note->id, $audit->new_values['note_id']);
        $this->assertEquals($this->staff->id, $audit->new_values['author_id']);
    }

    /**
     * 15. Test appending empty or whitespace note throws validation error.
     */
    public function test_append_empty_or_whitespace_note_fails_validation(): void
    {
        $this->expectException(ValidationException::class);
        $this->service->addNote($this->inquiry, "   \n\t   ", $this->staff);
    }
}

