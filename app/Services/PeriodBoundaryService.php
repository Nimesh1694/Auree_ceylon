<?php

namespace App\Services;

use Carbon\Carbon;
use InvalidArgumentException;

class PeriodBoundaryService
{
    public const TIMEZONE = 'Asia/Colombo';

    /**
     * Resolve period boundaries in Asia/Colombo and convert to UTC half-open range.
     *
     * @param string $period 'today' | 'this_month' | 'last_month' | 'last_30_days' | 'last_90_days' | 'this_year' | 'custom' | 'all_time'
     * @param string|null $customStart 'YYYY-MM-DD'
     * @param string|null $customEnd   'YYYY-MM-DD'
     * @return array{
     *     period: string,
     *     startUtc: string,
     *     endUtc: string,
     *     startColombo: string,
     *     endColombo: string,
     *     targetMonth: string,
     *     periodLabel: string
     * }
     */
    public function resolvePeriodUtcBoundaries(string $period = 'this_month', ?string $customStart = null, ?string $customEnd = null): array
    {
        $nowColombo = Carbon::now(self::TIMEZONE);

        switch ($period) {
            case 'today':
                $startColombo = $nowColombo->copy()->startOfDay();
                $endColombo = $nowColombo->copy()->addDay()->startOfDay();
                $label = 'Today (' . $startColombo->format('d M Y') . ')';
                $targetMonth = $startColombo->format('Y-m');
                break;

            case 'last_month':
                $startColombo = $nowColombo->copy()->subMonthNoOverflow()->startOfMonth();
                $endColombo = $startColombo->copy()->addMonthNoOverflow()->startOfMonth();
                $label = 'Last Month (' . $startColombo->format('F Y') . ')';
                $targetMonth = $startColombo->format('Y-m');
                break;

            case 'last_30_days':
                $startColombo = $nowColombo->copy()->subDays(30)->startOfDay();
                $endColombo = $nowColombo->copy()->addDay()->startOfDay();
                $label = 'Last 30 Days';
                $targetMonth = $nowColombo->format('Y-m');
                break;

            case 'last_90_days':
                $startColombo = $nowColombo->copy()->subDays(90)->startOfDay();
                $endColombo = $nowColombo->copy()->addDay()->startOfDay();
                $label = 'Last 90 Days';
                $targetMonth = $nowColombo->format('Y-m');
                break;

            case 'this_year':
                $startColombo = $nowColombo->copy()->startOfYear();
                $endColombo = $nowColombo->copy()->addYear()->startOfYear();
                $label = 'This Year (' . $startColombo->format('Y') . ')';
                $targetMonth = $nowColombo->format('Y-m');
                break;

            case 'custom':
                if (empty($customStart) || empty($customEnd)) {
                    throw new InvalidArgumentException('Custom period requires both start_date and end_date in YYYY-MM-DD format.');
                }
                $startColombo = Carbon::createFromFormat('Y-m-d', $customStart, self::TIMEZONE)->startOfDay();
                $endExclusive = Carbon::createFromFormat('Y-m-d', $customEnd, self::TIMEZONE)->addDay()->startOfDay();
                if ($startColombo->greaterThan($endExclusive)) {
                    throw new InvalidArgumentException('start_date cannot be after end_date.');
                }
                $endColombo = $endExclusive;
                $label = $startColombo->format('d M Y') . ' — ' . Carbon::createFromFormat('Y-m-d', $customEnd, self::TIMEZONE)->format('d M Y');
                $targetMonth = $startColombo->format('Y-m');
                break;

            case 'all_time':
                $startColombo = Carbon::create(2020, 1, 1, 0, 0, 0, self::TIMEZONE);
                $endColombo = Carbon::create(2099, 12, 31, 0, 0, 0, self::TIMEZONE);
                $label = 'All Time';
                $targetMonth = $nowColombo->format('Y-m');
                break;

            case 'this_month':
            default:
                $period = 'this_month';
                $startColombo = $nowColombo->copy()->startOfMonth();
                $endColombo = $nowColombo->copy()->addMonthNoOverflow()->startOfMonth();
                $label = 'This Month (' . $startColombo->format('F Y') . ')';
                $targetMonth = $startColombo->format('Y-m');
                break;
        }

        $startUtc = $startColombo->copy()->setTimezone('UTC')->format('Y-m-d H:i:s');
        $endUtc = $endColombo->copy()->setTimezone('UTC')->format('Y-m-d H:i:s');

        return [
            'period' => $period,
            'startUtc' => $startUtc,
            'endUtc' => $endUtc,
            'startColombo' => $startColombo->format('Y-m-d H:i:s'),
            'endColombo' => $endColombo->format('Y-m-d H:i:s'),
            'targetMonth' => $targetMonth,
            'periodLabel' => $label,
        ];
    }
}

