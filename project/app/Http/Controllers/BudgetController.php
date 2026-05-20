<?php
// app/Http/Controllers/Api/BudgetController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Budget\StoreBudgetRequest;
use App\Models\Budget;
use App\Services\BudgetService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * BudgetController
 * Monthly budget management with spending tracking
 */
class BudgetController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly BudgetService $budgetService
    ) {}

    /**
     * GET /api/budgets?month=&year=
     * Get all budgets with spending progress for a month
     */
    public function index(Request $request): JsonResponse
    {
        $month = (int) ($request->month ?? now()->month);
        $year  = (int) ($request->year  ?? now()->year);

        $budgets = $this->budgetService->getBudgetsWithSpending($request->user(), $month, $year);

        // Calculate overall budget summary
        $totalBudget = array_sum(array_column($budgets, 'budget_amount'));
        $totalSpent  = array_sum(array_column($budgets, 'spent_amount'));

        return $this->successResponse([
            'budgets' => $budgets,
            'summary' => [
                'total_budget'    => $totalBudget,
                'total_spent'     => $totalSpent,
                'total_remaining' => max(0, $totalBudget - $totalSpent),
                'overall_pct'     => $totalBudget > 0 ? round(($totalSpent / $totalBudget) * 100, 2) : 0,
                'month'           => $month,
                'year'            => $year,
            ],
        ], 'Budgets retrieved successfully');
    }

    /**
     * POST /api/budgets
     * Create or update a budget for a category/month
     */
    public function store(StoreBudgetRequest $request): JsonResponse
    {
        $budget = $this->budgetService->upsertBudget($request->user(), $request->validated());

        $spent = $this->budgetService->getSpentAmount(
            $request->user()->id,
            $budget->category_id,
            $budget->month,
            $budget->year
        );

        return $this->successResponse(
            $this->budgetService->formatBudgetWithSpending($budget, $spent),
            'Budget saved successfully',
            201
        );
    }

    /**
     * GET /api/budgets/{id}
     * Get a single budget with spending details
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $budget = Budget::with('category')
            ->where('user_id', $request->user()->id)
            ->find($id);

        if (!$budget) {
            return $this->notFoundResponse('Budget');
        }

        $spent = $this->budgetService->getSpentAmount(
            $request->user()->id,
            $budget->category_id,
            $budget->month,
            $budget->year
        );

        return $this->successResponse(
            $this->budgetService->formatBudgetWithSpending($budget, $spent),
            'Budget retrieved successfully'
        );
    }

    /**
     * DELETE /api/budgets/{id}
     * Remove a budget
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $budget = Budget::where('user_id', $request->user()->id)->find($id);

        if (!$budget) {
            return $this->notFoundResponse('Budget');
        }

        $budget->delete();

        return $this->successResponse(null, 'Budget deleted successfully');
    }

    /**
     * GET /api/budgets/alerts
     * Get all current budget alerts for this month
     */
    public function alerts(Request $request): JsonResponse
    {
        $month = (int) now()->month;
        $year  = (int) now()->year;

        $budgets = $this->budgetService->getBudgetsWithSpending($request->user(), $month, $year);

        $alerts = array_filter($budgets, fn($b) => in_array($b['status'], ['warning', 'exceeded']));

        return $this->successResponse(
            array_values($alerts),
            'Budget alerts retrieved'
        );
    }
}
