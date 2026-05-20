<?php
// app/Http/Controllers/Api/ReportController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ExportService;
use App\Services\ReportService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * ReportController
 * Financial reports and chart data for frontend visualization
 */
class ReportController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly ReportService $reportService,
        private readonly ExportService $exportService
    ) {}

    /**
     * GET /api/reports/monthly?month=&year=
     * Generate complete monthly financial report
     */
    public function monthly(Request $request): JsonResponse
    {
        $month = (int) ($request->month ?? now()->month);
        $year  = (int) ($request->year  ?? now()->year);

        if ($month < 1 || $month > 12) {
            return $this->errorResponse('Invalid month value (1-12)', 400);
        }

        $report = $this->reportService->generateMonthlyReport($request->user(), $month, $year);

        return $this->successResponse($report, 'Monthly report generated successfully');
    }

    /**
     * GET /api/reports/chart/income-vs-expense?months=6
     * Income vs Expense trend data for bar/line charts
     */
    public function incomeVsExpenseChart(Request $request): JsonResponse
    {
        $months = min((int) ($request->months ?? 6), 24); // cap at 24 months

        $data = $this->reportService->getIncomeVsExpenseChart($request->user(), $months);

        return $this->successResponse($data, 'Chart data retrieved successfully');
    }

    /**
     * GET /api/reports/chart/expense-by-category?month=&year=
     * Expense distribution by category for pie/doughnut charts
     */
    public function expenseByCategoryChart(Request $request): JsonResponse
    {
        $month = (int) ($request->month ?? now()->month);
        $year  = (int) ($request->year  ?? now()->year);

        $data = $this->reportService->getExpenseByCategory($request->user()->id, $month, $year);

        return $this->successResponse($data, 'Category chart data retrieved successfully');
    }

    /**
     * GET /api/reports/chart/daily?month=&year=
     * Daily income/expense breakdown for a month
     */
    public function dailyChart(Request $request): JsonResponse
    {
        $month = (int) ($request->month ?? now()->month);
        $year  = (int) ($request->year  ?? now()->year);

        $data = $this->reportService->getDailyBreakdown($request->user()->id, $month, $year);

        return $this->successResponse($data, 'Daily chart data retrieved successfully');
    }

    /**
     * GET /api/reports/export/csv?month=&year=
     * Export transactions as CSV file download
     */
    public function exportCSV(Request $request): Response
    {
        $month = (int) ($request->month ?? 0);
        $year  = (int) ($request->year  ?? 0);

        $export = $this->exportService->exportCSV($request->user(), $month, $year);

        return response($export['content'], 200, [
            'Content-Type'        => $export['mime'],
            'Content-Disposition' => "attachment; filename=\"{$export['filename']}\"",
            'Cache-Control'       => 'no-cache, no-store, must-revalidate',
        ]);
    }

    /**
     * GET /api/reports/export/json?month=&year=
     * Export report data as JSON (for frontend PDF generation)
     */
    public function exportJSON(Request $request): JsonResponse
    {
        $month = (int) ($request->month ?? now()->month);
        $year  = (int) ($request->year  ?? now()->year);

        $data = $this->exportService->exportReportData($request->user(), $month, $year);

        return $this->successResponse($data, 'Report data exported successfully');
    }
}
