<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Services\FinancialReportService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class FinancialReportController extends Controller
{
    public function __construct(
        protected FinancialReportService $reportService
    ) {}

    /**
     * Display the Executive Operating Contribution (P&L) Report.
     * Restricted strictly to Business Owner and Accountant authorities.
     */
    public function index(Request $request): Response|JsonResponse
    {
        $this->authorizeFinancialAccess($request);

        $validated = $this->validatePeriodParameters($request);

        $startDate = $validated['start_date'] ?? null;
        $endDate = $validated['end_date'] ?? null;
        $perspective = $validated['perspective'] ?? 'fulfilled_date';

        $report = $this->reportService->generateReport($startDate, $endDate, $perspective);

        $this->logReportAccess($request, $report, 'index');

        if ($request->wantsJson() || $request->query('format') === 'json') {
            return response()->json($report);
        }

        return Inertia::render('Admin/Finance/ContributionReport', [
            'report' => $report,
            'filters' => [
                'date_from' => $startDate,
                'date_to' => $endDate,
                'perspective' => $perspective,
            ],
        ]);
    }

    /**
     * Retrieve a concise executive summary of the Operating Contribution statement.
     * Restricted strictly to Business Owner and Accountant authorities.
     */
    public function summary(Request $request): JsonResponse
    {
        $this->authorizeFinancialAccess($request);

        $validated = $this->validatePeriodParameters($request);

        $startDate = $validated['start_date'] ?? null;
        $endDate = $validated['end_date'] ?? null;
        $perspective = $validated['perspective'] ?? 'fulfilled_date';

        $report = $this->reportService->generateReport($startDate, $endDate, $perspective);

        $this->logReportAccess($request, $report, 'summary');

        $summary = [
            'period' => $report['period'],
            'certification' => $report['certification'],
            'revenue' => [
                'net_recognized_product_revenue' => $report['revenue']['net_recognized_product_revenue'],
                'gross_product_sales' => $report['revenue']['gross_product_sales'],
                'returns_and_refunds' => $report['revenue']['returns_and_refunds'],
            ],
            'cogs' => [
                'audited_cogs' => $report['cogs']['audited_cogs'],
                'estimated_legacy_cogs' => $report['cogs']['estimated_legacy_cogs'],
                'total_cogs' => $report['cogs']['total_cogs'],
                'gross_product_profit' => $report['cogs']['gross_product_profit'],
                'gross_product_margin_percent' => $report['cogs']['gross_product_margin_percent'],
            ],
            'logistics' => [
                'collected_delivery_revenue' => $report['logistics']['collected_delivery_revenue'],
                'actual_courier_expense' => $report['logistics']['actual_courier_expense'],
                'logistics_contribution' => $report['logistics']['logistics_contribution'],
                'invoiced_deliveries_count' => $report['logistics']['invoiced_deliveries_count'],
                'missing_courier_fees_count' => $report['logistics']['missing_courier_fees_count'],
            ],
            'commissions' => [
                'matured_commission_expense' => $report['commissions']['matured_commission_expense'],
                'reversed_commission_credit' => $report['commissions']['reversed_commission_credit'],
                'net_commission_expense' => $report['commissions']['net_commission_expense'],
                'pending_grace_window_accrual' => $report['commissions']['pending_grace_window_accrual'],
            ],
            'cod_reconciliation' => [
                'recovered_amount' => $report['cod_reconciliation']['recovered_amount'],
                'confirmed_unrecoverable_loss' => $report['cod_reconciliation']['confirmed_unrecoverable_loss'],
                'unresolved_disputes_amount' => $report['cod_reconciliation']['unresolved_disputes_amount'],
            ],
            'contribution' => $report['contribution'],
        ];

        return response()->json($summary);
    }

    /**
     * Defense-in-depth Role Check: Strictly Business Owner and Accountant authorities.
     */
    protected function authorizeFinancialAccess(Request $request): void
    {
        $user = $request->user();

        if (!$user || !($user->isOwner() || $user->isAccountant())) {
            abort(403, 'Unauthorized access: Restricted to Business Owner and Accountant authorities only.');
        }
    }

    /**
     * Validate and normalize query period parameters.
     *
     * @return array{start_date: ?string, end_date: ?string, perspective: string}
     * @throws ValidationException
     */
    protected function validatePeriodParameters(Request $request): array
    {
        $data = $request->all();

        // Support both date_from/date_to and start_date/end_date aliases
        $rawStart = $data['date_from'] ?? $data['start_date'] ?? null;
        $rawEnd = $data['date_to'] ?? $data['end_date'] ?? null;
        $rawPerspective = $data['perspective'] ?? 'fulfilled_date';

        $normalized = [
            'start_date' => $rawStart,
            'end_date' => $rawEnd,
            'perspective' => $rawPerspective,
        ];

        $validator = Validator::make($normalized, [
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'perspective' => ['nullable', 'string', 'in:fulfilled_date,placed_date,cash_date'],
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $validated = $validator->validated();

        if ($validated['start_date'] && $validated['end_date']) {
            $start = Carbon::parse($validated['start_date']);
            $end = Carbon::parse($validated['end_date']);

            if ($start->diffInDays($end) > 1826) {
                throw ValidationException::withMessages([
                    'end_date' => ['The selected reporting period cannot exceed 5 years (1,826 days).'],
                ]);
            }
        }

        return [
            'start_date' => $validated['start_date'] ?? null,
            'end_date' => $validated['end_date'] ?? null,
            'perspective' => $validated['perspective'] ?? 'fulfilled_date',
        ];
    }

    /**
     * Immutable Audit Log of financial report access.
     *
     * @param array<string, mixed> $report
     */
    protected function logReportAccess(Request $request, array $report, string $endpoint): void
    {
        $user = $request->user();

        AuditLog::log(
            'financial_report_accessed',
            null,
            null,
            [
                'endpoint' => $endpoint,
                'period_start' => $report['period']['start_date'],
                'period_end' => $report['period']['end_date'],
                'perspective' => $report['period']['perspective'],
                'certification_status' => $report['certification']['status'],
                'gross_operating_contribution' => $report['contribution']['amount'],
                'accessed_by_user_id' => $user?->id,
                'accessed_by_role' => $user?->getRoleValue(),
                'ip_address' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 255),
            ]
        );
    }
}

