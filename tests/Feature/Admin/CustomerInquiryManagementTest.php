<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\CustomerInquiry;
use App\Models\CustomerInquiryNote;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CustomerInquiryManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $staff;
    protected User $customer;
    protected User $agent;
    protected CustomerInquiry $inquiry;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        // Create inquiries.manage permission and assign to operations_manager role
        $perm = Permission::firstOrCreate(['name' => 'inquiries.manage'], [
            'display_name' => 'Manage Customer Inquiries',
            'module' => 'support',
            'description' => 'Test',
            'is_financial_restricted' => false,
        ]);
        $opsRole = Role::where('name', 'operations_manager')->first();
        $opsRole->permissions()->syncWithoutDetaching([$perm->id]);

        $this->staff = User::factory()->create([
            'role' => UserRole::OPERATIONS_MANAGER,
            'role_id' => $opsRole->id,
            'status' => UserStatus::ACTIVE,
            'two_factor_confirmed_at' => now(),
        ]);
        $this->staff->assignedRoles()->sync([$opsRole->id]);

        $this->customer = User::factory()->create([
            'role' => UserRole::CUSTOMER,
            'status' => UserStatus::ACTIVE,
            'two_factor_confirmed_at' => now(),
        ]);

        $this->agent = User::factory()->create([
            'role' => UserRole::HIGH_LEVEL_AGENT,
            'status' => UserStatus::ACTIVE,
            'two_factor_confirmed_at' => now(),
        ]);

        $this->inquiry = CustomerInquiry::create([
            'inquiry_number' => 'INQ-20261111-FEAT1',
            'name' => 'Naveen Jayawardena',
            'email' => 'naveen@example.com',
            'phone' => '+94771112233',
            'subject' => 'Wrong Item Delivered',
            'message' => 'I received the nighttime cream instead of the day barrier cream.',
            'status' => CustomerInquiry::STATUS_NEW,
            'priority' => CustomerInquiry::PRIORITY_NORMAL,
            'ip_address' => '192.168.1.100',
            'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
        ]);
    }

    /**
     * 1. Test unauthenticated guest is redirected to login.
     */
    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get(route('admin.inquiries.index'));
        $response->assertRedirect(route('login'));

        $showResponse = $this->get(route('admin.inquiries.show', $this->inquiry));
        $showResponse->assertRedirect(route('login'));
    }

    /**
     * 2. Test customer and external agent receive 403 Forbidden.
     */
    public function test_customer_and_agent_are_denied_access_with_403(): void
    {
        // Customer
        $customerResponse = $this->actingAs($this->customer)->get(route('admin.inquiries.index'));
        $customerResponse->assertStatus(403);

        // Agent
        $agentResponse = $this->actingAs($this->agent)->get(route('admin.inquiries.index'));
        $agentResponse->assertStatus(403);
    }

    /**
     * 3. Test authorized staff can view listing and diagnostic PII is excluded.
     */
    public function test_authorized_staff_can_view_listing_and_diagnostic_pii_is_excluded(): void
    {
        $response = $this->actingAs($this->staff)->get(route('admin.inquiries.index'));

        $response->assertOk();
        $response->assertInertia(function (Assert $page) {
            $page->component('Admin/Inquiries/Index', false)
                ->has('inquiries.data', 1)
                ->where('inquiries.data.0.inquiry_number', 'INQ-20261111-FEAT1')
                ->missing('inquiries.data.0.ip_address')
                ->missing('inquiries.data.0.user_agent')
                ->has('stats')
                ->has('eligibleStaff');
        });
    }

    /**
     * 4. Test searching and filtering on index endpoint.
     */
    public function test_searching_and_filtering_on_index_endpoint(): void
    {
        CustomerInquiry::create([
            'inquiry_number' => 'INQ-SEARCH-999',
            'name' => 'Anura Kumara',
            'email' => 'anura@parliament.lk',
            'subject' => 'Official Inquiry',
            'message' => 'Official query message.',
            'status' => CustomerInquiry::STATUS_IN_PROGRESS,
            'priority' => CustomerInquiry::PRIORITY_URGENT,
        ]);

        // Search by inquiry number
        $searchResponse = $this->actingAs($this->staff)->get(route('admin.inquiries.index', ['search' => 'SEARCH-999']));
        $searchResponse->assertOk();
        $searchResponse->assertInertia(fn (Assert $page) => $page->has('inquiries.data', 1)->where('inquiries.data.0.inquiry_number', 'INQ-SEARCH-999'));

        // Filter by status
        $filterStatus = $this->actingAs($this->staff)->get(route('admin.inquiries.index', ['status' => CustomerInquiry::STATUS_IN_PROGRESS]));
        $filterStatus->assertOk();
        $filterStatus->assertInertia(fn (Assert $page) => $page->has('inquiries.data', 1));

        // Filter by priority
        $filterPriority = $this->actingAs($this->staff)->get(route('admin.inquiries.index', ['priority' => CustomerInquiry::PRIORITY_URGENT]));
        $filterPriority->assertOk();
        $filterPriority->assertInertia(fn (Assert $page) => $page->has('inquiries.data', 1));
    }

    /**
     * 5. Test show endpoint renders details, customer dossier, and omits diagnostic metadata.
     */
    public function test_show_endpoint_renders_details_and_omits_ip_and_user_agent(): void
    {
        $response = $this->actingAs($this->staff)->get(route('admin.inquiries.show', $this->inquiry));

        $response->assertOk();
        $response->assertInertia(function (Assert $page) {
            $page->component('Admin/Inquiries/Show', false)
                ->where('inquiry.inquiry_number', 'INQ-20261111-FEAT1')
                ->missing('inquiry.ip_address')
                ->missing('inquiry.user_agent')
                ->has('notes')
                ->has('eligibleStaff');
        });
    }

    /**
     * 6. Test updating status via PATCH endpoint.
     */
    public function test_updating_status_via_patch_endpoint(): void
    {
        $response = $this->actingAs($this->staff)->patch(route('admin.inquiries.status.update', $this->inquiry), [
            'status' => CustomerInquiry::STATUS_IN_PROGRESS,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->inquiry->refresh();
        $this->assertEquals(CustomerInquiry::STATUS_IN_PROGRESS, $this->inquiry->status);
    }

    /**
     * 7. Test prohibited status transition fails with 422 validation error.
     */
    public function test_prohibited_status_transition_fails_with_422(): void
    {
        // new -> resolved is prohibited
        $response = $this->actingAs($this->staff)->patch(route('admin.inquiries.status.update', $this->inquiry), [
            'status' => CustomerInquiry::STATUS_RESOLVED,
        ]);

        $response->assertSessionHasErrors(['status']);
        $this->inquiry->refresh();
        $this->assertEquals(CustomerInquiry::STATUS_NEW, $this->inquiry->status);
    }

    /**
     * 8. Test reopening closed inquiry without note fails validation.
     */
    public function test_reopening_closed_inquiry_without_note_fails_validation(): void
    {
        $this->inquiry->update(['status' => CustomerInquiry::STATUS_CLOSED]);

        $response = $this->actingAs($this->staff)->patch(route('admin.inquiries.status.update', $this->inquiry), [
            'status' => CustomerInquiry::STATUS_IN_PROGRESS,
            'reason' => '   ',
        ]);

        $response->assertSessionHasErrors(['reason']);
        $this->inquiry->refresh();
        $this->assertEquals(CustomerInquiry::STATUS_CLOSED, $this->inquiry->status);
    }

    /**
     * 9. Test reopening closed inquiry with valid note succeeds.
     */
    public function test_reopening_closed_inquiry_with_valid_note_succeeds(): void
    {
        $this->inquiry->update([
            'status' => CustomerInquiry::STATUS_CLOSED,
            'resolved_at' => now(),
            'resolved_by' => $this->staff->id,
        ]);

        $response = $this->actingAs($this->staff)->patch(route('admin.inquiries.status.update', $this->inquiry), [
            'status' => CustomerInquiry::STATUS_IN_PROGRESS,
            'reason' => 'Customer confirmed delivery courier returned parcel to warehouse.',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->inquiry->refresh();
        $this->assertEquals(CustomerInquiry::STATUS_IN_PROGRESS, $this->inquiry->status);
        $this->assertNull($this->inquiry->resolved_at);

        // Note is persisted
        $this->assertDatabaseHas('customer_inquiry_notes', [
            'customer_inquiry_id' => $this->inquiry->id,
            'user_id' => $this->staff->id,
        ]);
    }

    /**
     * 10. Test updating priority via PATCH endpoint.
     */
    public function test_updating_priority_via_patch_endpoint(): void
    {
        $response = $this->actingAs($this->staff)->patch(route('admin.inquiries.priority.update', $this->inquiry), [
            'priority' => CustomerInquiry::PRIORITY_HIGH,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->inquiry->refresh();
        $this->assertEquals(CustomerInquiry::PRIORITY_HIGH, $this->inquiry->priority);
    }

    /**
     * 11. Test assigning staff via PATCH endpoint.
     */
    public function test_assigning_staff_via_patch_endpoint(): void
    {
        $response = $this->actingAs($this->staff)->patch(route('admin.inquiries.assign', $this->inquiry), [
            'staff_user_id' => $this->staff->id,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->inquiry->refresh();
        $this->assertEquals($this->staff->id, $this->inquiry->assigned_to);
    }

    /**
     * 12. Test assigning customer or agent fails with 422 error.
     */
    public function test_assigning_customer_or_agent_fails_with_422(): void
    {
        $response = $this->actingAs($this->staff)->patch(route('admin.inquiries.assign', $this->inquiry), [
            'staff_user_id' => $this->customer->id,
        ]);

        $response->assertSessionHasErrors(['staff_user_id']);
        $this->inquiry->refresh();
        $this->assertNull($this->inquiry->assigned_to);
    }

    /**
     * 13. Test appending internal note via POST endpoint.
     */
    public function test_appending_internal_note_via_post_endpoint(): void
    {
        $response = $this->actingAs($this->staff)->post(route('admin.inquiries.notes.store', $this->inquiry), [
            'note' => 'Spoke with courier logistics coordinator; parcel being redirected.',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('customer_inquiry_notes', [
            'customer_inquiry_id' => $this->inquiry->id,
            'user_id' => $this->staff->id,
            'note' => 'Spoke with courier logistics coordinator; parcel being redirected.',
        ]);
    }

    /**
     * 14. Test appending empty note fails validation.
     */
    public function test_appending_empty_note_fails_validation(): void
    {
        $response = $this->actingAs($this->staff)->post(route('admin.inquiries.notes.store', $this->inquiry), [
            'note' => '',
        ]);

        $response->assertSessionHasErrors(['note']);
    }

    /**
     * 15. Test strictly verifying NO note update or delete routes exist.
     */
    public function test_no_note_update_or_delete_routes_exist(): void
    {
        $note = CustomerInquiryNote::create([
            'customer_inquiry_id' => $this->inquiry->id,
            'user_id' => $this->staff->id,
            'note' => 'Permanent note.',
        ]);

        // Attempting PUT /admin/inquiries/{inquiry}/notes/{note}
        $putResponse = $this->actingAs($this->staff)->put("/admin/inquiries/{$this->inquiry->id}/notes/{$note->id}", [
            'note' => 'Tampered.',
        ]);
        $this->assertTrue(in_array($putResponse->status(), [404, 405], true));

        // Attempting PATCH /admin/inquiries/{inquiry}/notes/{note}
        $patchResponse = $this->actingAs($this->staff)->patch("/admin/inquiries/{$this->inquiry->id}/notes/{$note->id}", [
            'note' => 'Tampered.',
        ]);
        $this->assertTrue(in_array($patchResponse->status(), [404, 405], true));

        // Attempting DELETE /admin/inquiries/{inquiry}/notes/{note}
        $deleteResponse = $this->actingAs($this->staff)->delete("/admin/inquiries/{$this->inquiry->id}/notes/{$note->id}");
        $this->assertTrue(in_array($deleteResponse->status(), [404, 405], true));
    }

    /**
     * 16. Test repeated staff assignment switching (Assign to Me -> Reassign -> Unassign -> Assign to Me -> Reassign).
     */
    public function test_repeated_assignment_switching_including_assign_to_me_reassign_and_unassign_succeeds(): void
    {
        $opsRole = Role::where('name', 'operations_manager')->first();
        $otherStaff = User::factory()->create([
            'role' => UserRole::OPERATIONS_MANAGER,
            'role_id' => $opsRole->id,
            'status' => UserStatus::ACTIVE,
            'two_factor_confirmed_at' => now(),
        ]);
        $otherStaff->assignedRoles()->sync([$opsRole->id]);

        // 1. Assign to Me (stringified ID as sent by Show.tsx)
        $this->actingAs($this->staff)
            ->patch(route('admin.inquiries.assign', $this->inquiry), ['staff_user_id' => (string) $this->staff->id])
            ->assertRedirect()
            ->assertSessionHas('success');
        $this->assertSame($this->staff->id, $this->inquiry->fresh()->assigned_to);

        // 2. Assign to another eligible staff member after Assign to Me
        $this->actingAs($this->staff)
            ->patch(route('admin.inquiries.assign', $this->inquiry), ['staff_user_id' => (string) $otherStaff->id])
            ->assertRedirect()
            ->assertSessionHas('success');
        $this->assertSame($otherStaff->id, $this->inquiry->fresh()->assigned_to);

        // 3. Assign to Me again, then Unassign (empty string converted to null)
        $this->actingAs($this->staff)
            ->patch(route('admin.inquiries.assign', $this->inquiry), ['staff_user_id' => (string) $this->staff->id])
            ->assertRedirect();
        $this->assertSame($this->staff->id, $this->inquiry->fresh()->assigned_to);

        $this->actingAs($this->staff)
            ->patch(route('admin.inquiries.assign', $this->inquiry), ['staff_user_id' => ''])
            ->assertRedirect()
            ->assertSessionHas('success');
        $this->assertNull($this->inquiry->fresh()->assigned_to);

        // 4. Repeated switching between staff members
        $this->actingAs($this->staff)
            ->patch(route('admin.inquiries.assign', $this->inquiry), ['staff_user_id' => (string) $otherStaff->id])
            ->assertRedirect();
        $this->assertSame($otherStaff->id, $this->inquiry->fresh()->assigned_to);

        $this->actingAs($this->staff)
            ->patch(route('admin.inquiries.assign', $this->inquiry), ['staff_user_id' => (string) $this->staff->id])
            ->assertRedirect();
        $this->assertSame($this->staff->id, $this->inquiry->fresh()->assigned_to);
    }

    /**
     * 17. Test users without inquiries.manage permission (including IT Manager) lack permission props and are denied 403.
     */
    public function test_staff_without_inquiries_manage_permission_is_denied_and_lacks_permission_prop(): void
    {
        $itRole = Role::where('name', 'it_manager')->first();
        $itManager = User::factory()->create([
            'role' => UserRole::IT_MANAGER,
            'role_id' => $itRole?->id,
            'status' => UserStatus::ACTIVE,
            'two_factor_confirmed_at' => now(),
        ]);
        if ($itRole) {
            $itManager->assignedRoles()->sync([$itRole->id]);
        }

        $this->assertFalse($itManager->hasPermissionTo('inquiries.manage'));
        $this->assertNotContains('inquiries.manage', $itManager->getPermissionsList());

        $this->actingAs($itManager)
            ->get(route('admin.inquiries.index'))
            ->assertStatus(403);
    }
}
