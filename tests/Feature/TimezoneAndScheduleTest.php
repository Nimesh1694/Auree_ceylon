<?php

namespace Tests\Feature;

use App\Models\Supplier;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TimezoneAndScheduleTest extends TestCase
{
    /**
     * Test A — Scheduler timezone.
     * Verify inventory:send-low-stock-digest is scheduled at 08:00 Asia/Colombo with withoutOverlapping.
     */
    public function test_low_stock_digest_schedule_is_configured_for_asia_colombo_timezone(): void
    {
        $schedule = $this->app->make(Schedule::class);

        $event = collect($schedule->events())->first(function ($event) {
            return str_contains((string) $event->command, 'inventory:send-low-stock-digest');
        });

        $this->assertNotNull($event, 'Scheduled event inventory:send-low-stock-digest should exist.');
        $this->assertSame('0 8 * * *', $event->expression, 'Digest should be scheduled with expression 0 8 * * * (dailyAt 08:00).');
        $this->assertSame('Asia/Colombo', $event->timezone, 'Digest event timezone should explicitly be Asia/Colombo.');
        $this->assertTrue($event->withoutOverlapping, 'Digest event should have withoutOverlapping enabled.');
    }

    /**
     * Test B — MySQL connection timezone.
     * Verify session time zone is +00:00 and NOW() matches UTC_TIMESTAMP().
     */
    public function test_mysql_connection_uses_utc_session_timezone(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            $this->markTestSkipped('This test verifies MySQL session timezone configuration.');
        }

        $sessionTz = DB::selectOne('SELECT @@session.time_zone AS tz')->tz;
        $this->assertSame('+00:00', $sessionTz, 'MySQL session timezone should explicitly be +00:00.');

        $times = DB::selectOne('SELECT NOW() AS db_now, UTC_TIMESTAMP() AS db_utc');
        $dbNow = Carbon::parse($times->db_now);
        $dbUtc = Carbon::parse($times->db_utc);
        $diff = abs($dbNow->diffInSeconds($dbUtc));
        $this->assertLessThanOrEqual(2, $diff, 'NOW() and UTC_TIMESTAMP() should represent the same time within 2 seconds.');
    }

    /**
     * Test C — Timestamp round-trip.
     * Verify stored and retrieved timestamps under MySQL match Carbon UTC without 5.5-hour skew.
     */
    public function test_timestamp_storage_and_retrieval_round_trip_without_skew(): void
    {
        DB::beginTransaction();

        try {
            $now = Carbon::now();

            $supplier = Supplier::create([
                'code' => 'TZ-TEST-' . uniqid(),
                'name' => 'Timezone Test Supplier',
                'status' => Supplier::STATUS_ACTIVE,
            ]);

            $this->assertInstanceOf(Carbon::class, $supplier->created_at);

            // Fetch raw stored timestamp string directly from database
            $rawRow = DB::selectOne('SELECT created_at, updated_at FROM suppliers WHERE id = ?', [$supplier->id]);
            $this->assertNotNull($rawRow);

            $rawCreatedAt = Carbon::parse($rawRow->created_at);

            // Difference between Carbon now and raw DB created_at must be within 5 seconds (not shifted by 5.5 hours!)
            $diffSeconds = abs($rawCreatedAt->diffInSeconds($now));
            $this->assertLessThanOrEqual(
                5,
                $diffSeconds,
                "Stored timestamp ({$rawRow->created_at}) should match application UTC time ({$now->toDateTimeString()}) without 5.5-hour offset."
            );

            // Re-query through Eloquent
            $fresh = Supplier::findOrFail($supplier->id);
            $this->assertSame($supplier->created_at->toDateTimeString(), $fresh->created_at->toDateTimeString());
        } finally {
            DB::rollBack();
        }
    }
}

