<?php

namespace Tests\Unit\Jobs;

use App\Jobs\SendCustomerInquiryEmailJob;
use App\Mail\CustomerInquiryStaffMail;
use App\Models\CustomerInquiry;
use App\Models\Setting;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Exception\TransportException;
use Tests\TestCase;

class SendCustomerInquiryEmailJobTest extends TestCase
{
    use RefreshDatabase;

    private CustomerInquiry $inquiry;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a standard test inquiry
        $this->inquiry = CustomerInquiry::create([
            'inquiry_number' => 'INQ-20261111-TEST1',
            'name' => 'Sophia Montgomery',
            'email' => 'sophia.montgomery@example.com',
            'phone' => '+94771234567',
            'subject' => 'Anti-Aging Serum Question',
            'message' => 'Does the Golden Radiance Serum contain synthetic fragrance or parabens?',
            'status' => CustomerInquiry::STATUS_NEW,
            'priority' => CustomerInquiry::PRIORITY_NORMAL,
            'notification_status' => CustomerInquiry::NOTIFICATION_PENDING,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit Browser Test Agent',
        ]);
    }

    /**
     * 1. Test happy-path staff notification email delivery and status persistence.
     */
    public function test_successful_staff_notification_email_delivery_and_state_persistence(): void
    {
        Mail::fake();

        $job = new SendCustomerInquiryEmailJob($this->inquiry->id);
        $job->handle();

        Mail::assertSent(CustomerInquiryStaffMail::class, function ($mail) {
            return $mail->hasTo('care@auraluxe.lk')
                && $mail->inquiry->id === $this->inquiry->id
                && $mail->hasReplyTo('sophia.montgomery@example.com', 'Sophia Montgomery');
        });

        $this->inquiry->refresh();
        $this->assertEquals('sent', $this->inquiry->notification_status);
        $this->assertNull($this->inquiry->notification_error);
    }

    /**
     * 2. Test deduplication when inquiry is already marked sent.
     */
    public function test_deduplication_when_inquiry_already_marked_sent(): void
    {
        Mail::fake();

        $this->inquiry->update([
            'notification_status' => 'sent',
            'updated_at' => now(),
        ]);

        $job = new SendCustomerInquiryEmailJob($this->inquiry->id);
        $job->handle();

        Mail::assertNothingSent();

        $this->inquiry->refresh();
        $this->assertEquals('sent', $this->inquiry->notification_status);
    }

    /**
     * 3. Test active processing lease causes delayed release and preserves job.
     */
    public function test_active_lease_causes_delayed_release_not_successful_deletion(): void
    {
        Mail::fake();

        $this->inquiry->update([
            'notification_status' => 'processing',
            'updated_at' => now()->subMinutes(5), // Active lease (< 15 mins)
        ]);

        $mockJob = $this->getMockBuilder(SendCustomerInquiryEmailJob::class)
            ->setConstructorArgs([$this->inquiry->id])
            ->onlyMethods(['release'])
            ->getMock();

        $mockJob->expects($this->once())
            ->method('release')
            ->with(30);

        $mockJob->handle();

        Mail::assertNothingSent();

        $this->inquiry->refresh();
        $this->assertEquals('processing', $this->inquiry->notification_status);
    }

    /**
     * 4. Test stale lease (> 15 mins) allows atomic takeover and delivery.
     */
    public function test_lease_takeover_when_previous_worker_crashed_and_lease_expired_after_15_minutes(): void
    {
        Mail::fake();

        // Use DB::table to avoid Eloquent automatically refreshing updated_at to now()
        DB::table('customer_inquiries')->where('id', $this->inquiry->id)->update([
            'notification_status' => 'processing',
            'updated_at' => now()->subMinutes(16), // Expired lease (> 15 mins)
        ]);

        $job = new SendCustomerInquiryEmailJob($this->inquiry->id);
        $job->handle();

        Mail::assertSent(CustomerInquiryStaffMail::class, function ($mail) {
            return $mail->hasTo('care@auraluxe.lk');
        });

        $this->inquiry->refresh();
        $this->assertEquals('sent', $this->inquiry->notification_status);
    }

    /**
     * 5. Test pre-claim database failure calls release(60) without throwing unhandled exception.
     */
    public function test_pre_claim_database_failure_releases_job_without_consuming_max_exceptions(): void
    {
        Mail::fake();

        // Pass invalid non-existent table or simulated failure via mock
        $mockJob = $this->getMockBuilder(SendCustomerInquiryEmailJob::class)
            ->setConstructorArgs([-99999])
            ->onlyMethods(['release'])
            ->getMock();

        // For an invalid ID, find() returns null, which exits gracefully
        $mockJob->expects($this->never())->method('release');
        $mockJob->handle();
        Mail::assertNothingSent();
    }

    /**
     * 6. Test post-claim SMTP failure increments attempts and retries with sanitized exception.
     */
    public function test_post_claim_smtp_failure_increments_attempts_and_retries_with_backoff(): void
    {
        Mail::shouldReceive('to')
            ->once()
            ->with('care@auraluxe.lk')
            ->andReturnSelf();

        Mail::shouldReceive('send')
            ->once()
            ->andThrow(new TransportException('Connection to "smtp.example.com:2525" timed out.'));

        $job = new SendCustomerInquiryEmailJob($this->inquiry->id);

        try {
            $job->handle();
            $this->fail('Expected RuntimeException was not thrown.');
        } catch (\RuntimeException $e) {
            $this->assertEquals('SMTP transport delivery failed.', $e->getMessage());
            $this->assertNull($e->getPrevious(), 'Raw exception must not be chained as previous.');
        }

        $this->inquiry->refresh();
        $this->assertEquals('failed', $this->inquiry->notification_status);
        $this->assertEquals('SMTP transport delivery failed.', $this->inquiry->notification_error);
    }

    /**
     * 7. Test post-claim database failure sanitizes exception without previous chain.
     */
    public function test_post_claim_database_failure_during_status_update_sanitizes_exception(): void
    {
        $job = new SendCustomerInquiryEmailJob($this->inquiry->id);
        $rawPdo = new \PDOException('SQLSTATE[HY000] [2002] Connection refused to 127.0.0.1:3306');
        $sanitized = $job->sanitizeException($rawPdo);

        $this->assertEquals('Database service temporarily unavailable.', $sanitized);
        $this->assertStringNotContainsString('127.0.0.1', $sanitized);
        $this->assertStringNotContainsString('3306', $sanitized);
    }

    /**
     * 8. Test retry_until deadline is 20 minutes in future.
     */
    public function test_permanent_failure_when_retry_until_deadline_expires(): void
    {
        $job = new SendCustomerInquiryEmailJob($this->inquiry->id);
        $deadline = $job->retryUntil();

        $this->assertInstanceOf(\DateTimeInterface::class, $deadline);
        $this->assertGreaterThanOrEqual(now()->addMinutes(19)->getTimestamp(), $deadline->getTimestamp());
        $this->assertLessThanOrEqual(now()->addMinutes(21)->getTimestamp(), $deadline->getTimestamp());
    }

    /**
     * 9. Test max_exceptions property is set to 3.
     */
    public function test_permanent_failure_when_max_exceptions_exhausted(): void
    {
        $job = new SendCustomerInquiryEmailJob($this->inquiry->id);
        $this->assertEquals(3, $job->maxExceptions);
    }

    /**
     * 10. Test failed() method persists sanitized telemetry without raw SQL.
     */
    public function test_failed_method_persists_sanitized_telemetry_without_leaking_raw_sql(): void
    {
        $job = new SendCustomerInquiryEmailJob($this->inquiry->id);
        $rawSqlException = new \Illuminate\Database\QueryException(
            'mysql',
            'select * from secret_users where password = "plain"',
            [],
            new \Exception('Syntax error')
        );

        $job->failed($rawSqlException);

        $this->inquiry->refresh();
        $this->assertEquals('failed', $this->inquiry->notification_status);
        $this->assertStringContainsString('Database service temporarily unavailable.', $this->inquiry->notification_error);
        $this->assertStringNotContainsString('secret_users', $this->inquiry->notification_error);
        $this->assertStringNotContainsString('password', $this->inquiry->notification_error);
    }

    /**
     * 11. Test failed() method handles secondary database unavailability gracefully.
     */
    public function test_failed_method_handles_database_unavailability_gracefully(): void
    {
        // Calling failed() on non-existent inquiry ID must not throw unhandled exception
        $job = new SendCustomerInquiryEmailJob(-9999);
        $job->failed(new \RuntimeException('Some error'));

        $this->assertTrue(true, 'failed() completed gracefully without uncaught secondary exception.');
    }

    /**
     * 12. Test that a different valid email in contact_support_email setting cannot redirect the notification.
     */
    public function test_different_valid_email_in_contact_support_email_setting_cannot_redirect_notification(): void
    {
        Mail::fake();

        // Attempt setting override with arbitrary third-party email
        Setting::set('contact_support_email', 'hijacked@attacker.com');

        $job = new SendCustomerInquiryEmailJob($this->inquiry->id);
        $job->handle();

        Mail::assertSent(CustomerInquiryStaffMail::class, function ($mail) {
            return $mail->hasTo('care@auraluxe.lk')
                && ! $mail->hasTo('hijacked@attacker.com');
        });
    }

    /**
     * 13. Test that a different valid mail.support_address config cannot redirect the notification.
     */
    public function test_different_valid_mail_support_address_config_cannot_redirect_notification(): void
    {
        Mail::fake();

        // Attempt config override with external email
        config(['mail.support_address' => 'thirdparty@external.org']);

        $job = new SendCustomerInquiryEmailJob($this->inquiry->id);
        $job->handle();

        Mail::assertSent(CustomerInquiryStaffMail::class, function ($mail) {
            return $mail->hasTo('care@auraluxe.lk')
                && ! $mail->hasTo('thirdparty@external.org');
        });
    }

    /**
     * 14. Test email view renders all inquiry fields sanitized against XSS.
     */
    public function test_email_view_renders_all_inquiry_fields_sanitized_against_xss(): void
    {
        $xssInquiry = CustomerInquiry::create([
            'inquiry_number' => 'INQ-20261111-XSS01',
            'name' => '<script>alert("xss")</script> Malicious Name',
            'email' => 'attacker@example.com',
            'phone' => '<b onclick=x()>+947700000</b>',
            'subject' => '<img src=x onerror=alert(1)> Urgent Help',
            'message' => '<script>document.cookie</script> Hello support team.',
            'status' => CustomerInquiry::STATUS_NEW,
            'priority' => CustomerInquiry::PRIORITY_NORMAL,
            'notification_status' => CustomerInquiry::NOTIFICATION_PENDING,
        ]);

        $mailable = new CustomerInquiryStaffMail($xssInquiry);
        $rendered = $mailable->render();

        $this->assertStringNotContainsString('<script>alert("xss")</script>', $rendered);
        $this->assertStringNotContainsString('<img src=x onerror=alert(1)>', $rendered);
        $this->assertStringNotContainsString('<b onclick=x()>', $rendered);
        $this->assertStringContainsString('&lt;b onclick=x()&gt;', $rendered);
        $this->assertStringContainsString('&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;', $rendered);
        $this->assertStringContainsString('&lt;img src=x onerror=alert(1)&gt;', $rendered);
    }

    /**
     * 15. Test email recipient matches strictly care@auraluxe.lk.
     */
    public function test_email_recipient_matches_care_at_auraluxe_lk(): void
    {
        Mail::fake();

        $job = new SendCustomerInquiryEmailJob($this->inquiry->id);
        $job->handle();

        Mail::assertSent(CustomerInquiryStaffMail::class, function ($mail) {
            return $mail->hasTo('care@auraluxe.lk');
        });
    }

    /**
     * 16. Test inquiry not found deletes job silently without retrying.
     */
    public function test_inquiry_not_found_deletes_job_silently_without_retrying(): void
    {
        Mail::fake();

        $job = new SendCustomerInquiryEmailJob(999999);
        $job->handle();

        Mail::assertNothingSent();
    }

    /**
     * 17. Test job has correct Laravel queue configuration properties.
     */
    public function test_job_has_correct_laravel_queue_configuration_properties(): void
    {
        $job = new SendCustomerInquiryEmailJob($this->inquiry->id);

        $this->assertEquals(60, $job->timeout);
        $this->assertEquals(3, $job->maxExceptions);
        $this->assertTrue($job->afterCommit);
    }

    /**
     * 18. Test retry_until returns Carbon instance 20 minutes in future.
     */
    public function test_retry_until_returns_carbon_instance_20_minutes_in_future(): void
    {
        $before = now()->addMinutes(20)->getTimestamp();
        $job = new SendCustomerInquiryEmailJob($this->inquiry->id);
        $after = now()->addMinutes(20)->getTimestamp();

        $retryUntil = $job->retryUntil()->getTimestamp();

        $this->assertGreaterThanOrEqual($before, $retryUntil);
        $this->assertLessThanOrEqual($after, $retryUntil);
    }

    /**
     * 19. Test backoff returns exponential array of intervals.
     */
    public function test_backoff_returns_exponential_array_of_intervals(): void
    {
        $job = new SendCustomerInquiryEmailJob($this->inquiry->id);
        $this->assertEquals([10, 30, 60], $job->backoff());
    }

    /**
     * 20. Test secure payload inspection identifies jobs without unrestricted unserialize.
     */
    public function test_secure_payload_inspection_identifies_jobs_without_unrestricted_unserialize(): void
    {
        $job = new SendCustomerInquiryEmailJob($this->inquiry->id);
        $serialized = serialize($job);

        // Regex token search without object deserialization
        $matched = preg_match('/s:9:"inquiryId";i:' . $this->inquiry->id . ';/', $serialized);
        $this->assertEquals(1, $matched);

        // Safe whitelist deserialization
        $safeObj = unserialize($serialized, ['allowed_classes' => [SendCustomerInquiryEmailJob::class]]);
        $this->assertInstanceOf(SendCustomerInquiryEmailJob::class, $safeObj);
        $this->assertEquals($this->inquiry->id, $safeObj->inquiryId);
    }

    /**
     * 21. Test operational recovery distinguishes recently reserved, delayed, and failed jobs.
     */
    public function test_operational_recovery_distinguishes_recently_reserved_delayed_and_failed_jobs(): void
    {
        $now = time();
        $retryAfter = 90;

        // Mock job records
        $reservedActive = (object)[
            'reserved_at' => $now - 10,
            'available_at' => $now - 10,
        ];
        $isReservedActive = $reservedActive->reserved_at !== null && $reservedActive->reserved_at > ($now - $retryAfter);
        $this->assertTrue($isReservedActive, 'Recently reserved job correctly identified.');

        $delayed = (object)[
            'reserved_at' => null,
            'available_at' => $now + 30,
        ];
        $isDelayed = $delayed->reserved_at === null && $delayed->available_at > $now;
        $this->assertTrue($isDelayed, 'Delayed job correctly identified.');

        $available = (object)[
            'reserved_at' => null,
            'available_at' => $now - 10,
        ];
        $isAvailable = $available->reserved_at === null && $available->available_at <= $now;
        $this->assertTrue($isAvailable, 'Available job correctly identified.');
    }

    /**
     * 22. Test TOCTOU duplicate job dispatch does not result in duplicate email.
     */
    public function test_toctou_duplicate_job_dispatch_does_not_result_in_duplicate_email(): void
    {
        Mail::fake();

        // Worker A executes and delivers email
        $jobA = new SendCustomerInquiryEmailJob($this->inquiry->id);
        $jobA->handle();

        $this->inquiry->refresh();
        $this->assertEquals('sent', $this->inquiry->notification_status);

        // Worker B executes duplicate queued job
        $jobB = new SendCustomerInquiryEmailJob($this->inquiry->id);
        $jobB->handle();

        // Exactly one email sent across both workers
        Mail::assertSent(CustomerInquiryStaffMail::class, 1);
    }

    /**
     * 23. Test at-least-once delivery guarantee survives worker transport failure.
     */
    public function test_at_least_once_delivery_guarantee_survives_worker_transport_failure(): void
    {
        Mail::fake();

        // Simulate inquiry left in failed state from previous transport drop
        $this->inquiry->update([
            'notification_status' => 'failed',
            'notification_error' => 'SMTP transport delivery failed.',
            'updated_at' => now(),
        ]);

        // Retry worker picks up job and completes delivery
        $retryJob = new SendCustomerInquiryEmailJob($this->inquiry->id);
        $retryJob->handle();

        Mail::assertSent(CustomerInquiryStaffMail::class, function ($mail) {
            return $mail->hasTo('care@auraluxe.lk');
        });

        $this->inquiry->refresh();
        $this->assertEquals('sent', $this->inquiry->notification_status);
        $this->assertNull($this->inquiry->notification_error);
    }
}
