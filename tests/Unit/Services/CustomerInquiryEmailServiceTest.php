<?php

namespace Tests\Unit\Services;

use App\Jobs\SendCustomerInquiryEmailJob;
use App\Models\CustomerInquiry;
use App\Services\CustomerInquiryEmailService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CustomerInquiryEmailServiceTest extends TestCase
{
    use RefreshDatabase;

    private CustomerInquiry $inquiry;
    private CustomerInquiryEmailService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->inquiry = CustomerInquiry::create([
            'inquiry_number' => 'INQ-20261111-SRV01',
            'name' => 'Giselle Vance',
            'email' => 'giselle@example.com',
            'phone' => '+94771112233',
            'subject' => 'Service Dispatch Test',
            'message' => 'Testing customer inquiry email service dispatch logic.',
            'status' => CustomerInquiry::STATUS_NEW,
            'priority' => CustomerInquiry::PRIORITY_NORMAL,
            'notification_status' => CustomerInquiry::NOTIFICATION_PENDING,
        ]);

        $this->service = app(CustomerInquiryEmailService::class);
    }

    /**
     * Test successful job dispatch for pending inquiry.
     */
    public function test_dispatches_notification_job_for_pending_inquiry(): void
    {
        Queue::fake([SendCustomerInquiryEmailJob::class]);

        $dispatched = $this->service->dispatchStaffNotification($this->inquiry);

        $this->assertTrue($dispatched);

        Queue::assertPushed(SendCustomerInquiryEmailJob::class, function ($job) {
            return $job->inquiryId === $this->inquiry->id;
        });
    }

    /**
     * Test service returns false and skips dispatch if inquiry is already sent.
     */
    public function test_skips_dispatch_when_inquiry_already_sent(): void
    {
        Queue::fake([SendCustomerInquiryEmailJob::class]);

        $this->inquiry->update([
            'notification_status' => 'sent',
            'updated_at' => now(),
        ]);

        $dispatched = $this->service->dispatchStaffNotification($this->inquiry);

        $this->assertFalse($dispatched);
        Queue::assertNothingPushed();
    }
}

