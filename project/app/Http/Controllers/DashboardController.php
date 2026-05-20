<?php
// app/Http/Controllers/Api/DashboardController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\BudgetService;
use App\Services\ReportService;
use App\Services\TransactionService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * DashboardController
 * Single endpoint that aggregates all dashboard data in one request
 * Reduces frontend API calls from 5+ to 1
 */
class DashboardController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly TransactionService $transactionService,
        private readonly BudgetService      $budgetService,
        private readonly ReportService      $reportService
    ) {}

    /**
     * GET /api/dashboard
     * Returns all data needed for the main dashboard in one call
     */
    public function index(Request $request): JsonResponse
    {
        $user  = $request->user();
        $month = (int) now()->month;
        $year  = (int) now()->year;

        // Run queries in parallel conceptually (all within one request)
        $summary         = $this->transactionService->getSummary($user, $month, $year);
        $budgets         = $this->budgetService->getBudgetsWithSpending($user, $month, $year);
        $chartData       = $this->reportService->getIncomeVsExpenseChart($user, 6);
        $expenseBycat    = $this->reportService->getExpenseByCategory($user->id, $month, $year);
        $recentTxns      = \App\Models\Transaction::with('category')
                            ->where('user_id', $user->id)
                            ->orderBy('transaction_date', 'desc')
                            ->limit(5)
                            ->get()
                            ->map(fn($t) => $this->transactionService->formatTransaction($t));

        // Budget alerts
        $budgetAlerts = array_filter($budgets, fn($b) => in_array($b['status'], ['warning', 'exceeded']));

        // Unread notifications count
        $unreadNotifs = \App\Models\Notification::where('user_id', $user->id)
                        ->unread()->count();

        return $this->successResponse([
            'summary'             => $summary,
            'budgets'             => $budgets,
            'budget_alerts'       => array_values($budgetAlerts),
            'chart_income_vs_exp' => $chartData,
            'chart_expense_by_cat'=> $expenseBycat,
            'recent_transactions' => $recentTxns,
            'unread_notifications'=> $unreadNotifs,
            'period'              => ['month' => $month, 'year' => $year],
        ], 'Dashboard data retrieved successfully');
    }

    /**
     * GET /api/notifications
     * Get user notifications with pagination
     */
    public function notifications(Request $request): JsonResponse
    {
        $notifications = \App\Models\Notification::where('user_id', $request->user()->id)
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        // Mark as read
        \App\Models\Notification::where('user_id', $request->user()->id)
            ->where('is_read', false)
            ->update(['is_read' => true, 'read_at' => now()]);

        return $this->paginatedResponse($notifications, 'Notifications retrieved');
    }
}
