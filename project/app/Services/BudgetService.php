<?php
// app/Services/BudgetService.php

namespace App\Services;

use App\Models\Budget;
use App\Models\Notification;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * BudgetService
 * Budget management and alert generation
 */
class BudgetService
{
    /**
     * Get all budgets for a user in a given month with spending data
     *
     * @param  User $user
     * @param  int  $month
     * @param  int  $year
     * @return array
     */
    public function getBudgetsWithSpending(User $user, int $month, int $year): array
    {
        $budgets = Budget::with('category')
            ->where('user_id', $user->id)
            ->forMonth($month, $year)
            ->active()
            ->get();

        return $budgets->map(function (Budget $budget) use ($user, $month, $year) {
            $spent = $this->getSpentAmount($user->id, $budget->category_id, $month, $year);
            return $this->formatBudgetWithSpending($budget, $spent);
        })->toArray();
    }

    /**
     * Create or update a budget for a category/month/year
     *
     * @param  User  $user
     * @param  array $data
     * @return Budget
     */
    public function upsertBudget(User $user, array $data): Budget
    {
        $budget = Budget::updateOrCreate(
            [
                'user_id'     => $user->id,
                'category_id' => $data['category_id'],
                'month'       => $data['month'],
                'year'        => $data['year'],
            ],
            [
                'amount'          => $data['amount'],
                'alert_threshold' => $data['alert_threshold'] ?? 80.00,
                'is_active'       => true,
            ]
        );

        $budget->load('category');
        return $budget;
    }

    /**
     * Check budget alerts after a transaction is added
     * Creates notification records and returns alert data
     *
     * @param  User        $user
     * @param  Transaction $transaction
     * @return array       Array of alert objects
     */
    public function checkBudgetAlerts(User $user, Transaction $transaction): array
    {
        if (!$user->budget_alerts) {
            return [];
        }

        $date  = $transaction->transaction_date;
        $month = (int) $date->format('m');
        $year  = (int) $date->format('Y');

        $budget = Budget::with('category')
            ->where('user_id', $user->id)
            ->where('category_id', $transaction->category_id)
            ->forMonth($month, $year)
            ->active()
            ->first();

        if (!$budget) {
            return [];
        }

        $spent      = $this->getSpentAmount($user->id, $transaction->category_id, $month, $year);
        $percentage = ($spent / $budget->amount) * 100;
        $alerts     = [];

        if ($percentage >= 100) {
            // Budget exceeded
            $alertKey = "exceeded_{$budget->id}_{$month}_{$year}";
            if (!$this->alertExists($user->id, $alertKey)) {
                $notification = $this->createNotification($user, [
                    'type'    => 'budget_exceeded',
                    'title'   => "Budget Exceeded: {$budget->category->name}",
                    'message' => "You've exceeded your {$budget->category->name} budget for " .
                                 date('F Y', mktime(0, 0, 0, $month, 1, $year)) .
                                 ". Spent: " . number_format($spent, 2) .
                                 " / Budget: " . number_format($budget->amount, 2),
                    'data'    => [
                        'alert_key'   => $alertKey,
                        'budget_id'   => $budget->id,
                        'category'    => $budget->category->name,
                        'spent'       => $spent,
                        'budget'      => (float) $budget->amount,
                        'percentage'  => round($percentage, 2),
                        'overage'     => round($spent - $budget->amount, 2),
                    ],
                ]);
                $alerts[] = ['severity' => 'danger', 'notification_id' => $notification->id,
                             'message'  => $notification->message];
            }
        } elseif ($percentage >= $budget->alert_threshold) {
            // Approaching limit
            $alertKey = "warning_{$budget->id}_{$month}_{$year}";
            if (!$this->alertExists($user->id, $alertKey)) {
                $notification = $this->createNotification($user, [
                    'type'    => 'budget_warning',
                    'title'   => "Budget Warning: {$budget->category->name}",
                    'message' => "You've used " . round($percentage, 1) . "% of your " .
                                 "{$budget->category->name} budget. " .
                                 number_format($budget->amount - $spent, 2) . " remaining.",
                    'data'    => [
                        'alert_key'  => $alertKey,
                        'budget_id'  => $budget->id,
                        'category'   => $budget->category->name,
                        'spent'      => $spent,
                        'budget'     => (float) $budget->amount,
                        'percentage' => round($percentage, 2),
                        'remaining'  => round($budget->amount - $spent, 2),
                    ],
                ]);
                $alerts[] = ['severity' => 'warning', 'notification_id' => $notification->id,
                             'message'  => $notification->message];
            }
        }

        return $alerts;
    }

    /**
     * Get total spent in a category for a given month
     */
    public function getSpentAmount(int $userId, int $categoryId, int $month, int $year): float
    {
        return (float) Transaction::where('user_id', $userId)
            ->where('category_id', $categoryId)
            ->where('type', 'expense')
            ->forMonth($month, $year)
            ->sum('amount');
    }

    /**
     * Format budget with spending progress data
     */
    public function formatBudgetWithSpending(Budget $budget, float $spent): array
    {
        $percentage = $budget->amount > 0 ? ($spent / $budget->amount) * 100 : 0;
        $remaining  = max(0, $budget->amount - $spent);
        $status     = match (true) {
            $percentage >= 100 => 'exceeded',
            $percentage >= $budget->alert_threshold => 'warning',
            default => 'ok',
        };

        return [
            'id'              => $budget->id,
            'category'        => $budget->category ? [
                'id'    => $budget->category->id,
                'name'  => $budget->category->name,
                'icon'  => $budget->category->icon,
                'color' => $budget->category->color,
            ] : null,
            'budget_amount'   => (float) $budget->amount,
            'spent_amount'    => $spent,
            'remaining'       => $remaining,
            'percentage_used' => round($percentage, 2),
            'alert_threshold' => (float) $budget->alert_threshold,
            'status'          => $status,
            'month'           => $budget->month,
            'year'            => $budget->year,
        ];
    }

    /**
     * Check if a specific alert was already sent (prevents duplicate notifications)
     */
    private function alertExists(int $userId, string $alertKey): bool
    {
        return Notification::where('user_id', $userId)
            ->whereJsonContains('data->alert_key', $alertKey)
            ->exists();
    }

    /**
     * Create and persist a notification
     */
    private function createNotification(User $user, array $data): Notification
    {
        return Notification::create([
            'user_id' => $user->id,
            'type'    => $data['type'],
            'title'   => $data['title'],
            'message' => $data['message'],
            'data'    => $data['data'],
        ]);
    }
}
