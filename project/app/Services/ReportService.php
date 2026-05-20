<?php
// app/Services/ReportService.php

namespace App\Services;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * ReportService
 * Financial report generation and chart data aggregation
 */
class ReportService
{
    /**
     * Generate a complete monthly financial report
     *
     * @param  User $user
     * @param  int  $month
     * @param  int  $year
     * @return array
     */
    public function generateMonthlyReport(User $user, int $month, int $year): array
    {
        $transactions = Transaction::with('category')
            ->where('user_id', $user->id)
            ->forMonth($month, $year)
            ->orderBy('transaction_date')
            ->get();

        $income  = $transactions->where('type', 'income')->sum('amount');
        $expense = $transactions->where('type', 'expense')->sum('amount');
        $balance = $income - $expense;

        return [
            'period' => [
                'month'      => $month,
                'year'       => $year,
                'month_name' => date('F', mktime(0, 0, 0, $month, 1, $year)),
            ],
            'summary' => [
                'total_income'      => (float) $income,
                'total_expense'     => (float) $expense,
                'net_balance'       => (float) $balance,
                'savings_rate'      => $income > 0 ? round(($balance / $income) * 100, 2) : 0,
                'transaction_count' => $transactions->count(),
                'avg_daily_expense' => $this->getAvgDailyExpense($user->id, $month, $year),
            ],
            'expense_by_category'    => $this->getExpenseByCategory($user->id, $month, $year),
            'income_by_category'     => $this->getIncomeByCategory($user->id, $month, $year),
            'daily_breakdown'        => $this->getDailyBreakdown($user->id, $month, $year),
            'top_expenses'           => $this->getTopExpenses($user->id, $month, $year, 5),
            'comparison_last_month'  => $this->compareWithLastMonth($user->id, $month, $year),
        ];
    }

    /**
     * Get income vs expense data for last N months (for bar/line charts)
     *
     * @param  User $user
     * @param  int  $months  Number of months to look back
     * @return array
     */
    public function getIncomeVsExpenseChart(User $user, int $months = 6): array
    {
        $data = [];

        for ($i = $months - 1; $i >= 0; $i--) {
            $date  = now()->subMonths($i);
            $month = (int) $date->format('m');
            $year  = (int) $date->format('Y');

            $totals = Transaction::where('user_id', $user->id)
                ->forMonth($month, $year)
                ->select(
                    DB::raw("SUM(CASE WHEN type='income'  THEN amount ELSE 0 END) as income"),
                    DB::raw("SUM(CASE WHEN type='expense' THEN amount ELSE 0 END) as expense")
                )
                ->first();

            $data[] = [
                'month'   => $month,
                'year'    => $year,
                'label'   => $date->format('M Y'),
                'income'  => (float) ($totals->income  ?? 0),
                'expense' => (float) ($totals->expense ?? 0),
                'balance' => (float) (($totals->income ?? 0) - ($totals->expense ?? 0)),
            ];
        }

        return $data;
    }

    /**
     * Get expense distribution by category (for pie/doughnut chart)
     *
     * @param  int $userId
     * @param  int $month
     * @param  int $year
     * @return array
     */
    public function getExpenseByCategory(int $userId, int $month, int $year): array
    {
        return Transaction::with('category')
            ->where('user_id', $userId)
            ->where('type', 'expense')
            ->forMonth($month, $year)
            ->select('category_id', DB::raw('SUM(amount) as total'), DB::raw('COUNT(*) as count'))
            ->groupBy('category_id')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => [
                'category_id'   => $row->category_id,
                'category_name' => $row->category?->name ?? 'Unknown',
                'icon'          => $row->category?->icon ?? 'tag',
                'color'         => $row->category?->color ?? '#6b7280',
                'total'         => (float) $row->total,
                'count'         => (int) $row->count,
                'percentage'    => 0, // calculated below
            ])
            ->pipe(function ($collection) {
                $grandTotal = $collection->sum('total');
                return $collection->map(function ($item) use ($grandTotal) {
                    $item['percentage'] = $grandTotal > 0
                        ? round(($item['total'] / $grandTotal) * 100, 2)
                        : 0;
                    return $item;
                });
            })
            ->values()
            ->toArray();
    }

    /**
     * Get income breakdown by category
     */
    public function getIncomeByCategory(int $userId, int $month, int $year): array
    {
        return Transaction::with('category')
            ->where('user_id', $userId)
            ->where('type', 'income')
            ->forMonth($month, $year)
            ->select('category_id', DB::raw('SUM(amount) as total'), DB::raw('COUNT(*) as count'))
            ->groupBy('category_id')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => [
                'category_id'   => $row->category_id,
                'category_name' => $row->category?->name ?? 'Unknown',
                'icon'          => $row->category?->icon ?? 'tag',
                'color'         => $row->category?->color ?? '#10b981',
                'total'         => (float) $row->total,
                'count'         => (int) $row->count,
            ])
            ->toArray();
    }

    /**
     * Get daily income/expense totals for a month (for daily chart)
     */
    public function getDailyBreakdown(int $userId, int $month, int $year): array
    {
        $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
        $rows = Transaction::where('user_id', $userId)
            ->forMonth($month, $year)
            ->select(
                DB::raw('DAY(transaction_date) as day'),
                DB::raw("SUM(CASE WHEN type='income'  THEN amount ELSE 0 END) as income"),
                DB::raw("SUM(CASE WHEN type='expense' THEN amount ELSE 0 END) as expense")
            )
            ->groupBy('day')
            ->orderBy('day')
            ->get()
            ->keyBy('day');

        $breakdown = [];
        for ($day = 1; $day <= $daysInMonth; $day++) {
            $row = $rows->get($day);
            $breakdown[] = [
                'day'     => $day,
                'date'    => sprintf('%04d-%02d-%02d', $year, $month, $day),
                'income'  => (float) ($row?->income  ?? 0),
                'expense' => (float) ($row?->expense ?? 0),
            ];
        }

        return $breakdown;
    }

    /**
     * Get top N expenses for a month
     */
    public function getTopExpenses(int $userId, int $month, int $year, int $limit = 5): array
    {
        return Transaction::with('category')
            ->where('user_id', $userId)
            ->where('type', 'expense')
            ->forMonth($month, $year)
            ->orderByDesc('amount')
            ->limit($limit)
            ->get()
            ->map(fn ($t) => [
                'id'               => $t->id,
                'description'      => $t->description,
                'amount'           => (float) $t->amount,
                'date'             => $t->transaction_date->toDateString(),
                'category'         => $t->category?->name ?? 'Unknown',
                'category_icon'    => $t->category?->icon ?? 'tag',
            ])
            ->toArray();
    }

    /**
     * Compare current month with previous month
     */
    public function compareWithLastMonth(int $userId, int $month, int $year): array
    {
        $prevDate  = \Carbon\Carbon::createFromDate($year, $month, 1)->subMonth();
        $prevMonth = (int) $prevDate->format('m');
        $prevYear  = (int) $prevDate->format('Y');

        $current = Transaction::where('user_id', $userId)->forMonth($month, $year)
            ->select(
                DB::raw("SUM(CASE WHEN type='income'  THEN amount ELSE 0 END) as income"),
                DB::raw("SUM(CASE WHEN type='expense' THEN amount ELSE 0 END) as expense")
            )->first();

        $previous = Transaction::where('user_id', $userId)->forMonth($prevMonth, $prevYear)
            ->select(
                DB::raw("SUM(CASE WHEN type='income'  THEN amount ELSE 0 END) as income"),
                DB::raw("SUM(CASE WHEN type='expense' THEN amount ELSE 0 END) as expense")
            )->first();

        $curIncome  = (float) ($current->income  ?? 0);
        $curExpense = (float) ($current->expense ?? 0);
        $prvIncome  = (float) ($previous->income  ?? 0);
        $prvExpense = (float) ($previous->expense ?? 0);

        return [
            'income_change'  => $this->calcChange($curIncome,  $prvIncome),
            'expense_change' => $this->calcChange($curExpense, $prvExpense),
            'previous_month' => $prevDate->format('F Y'),
        ];
    }

    /**
     * Average daily expense for a month
     */
    private function getAvgDailyExpense(int $userId, int $month, int $year): float
    {
        $total = Transaction::where('user_id', $userId)
            ->where('type', 'expense')
            ->forMonth($month, $year)
            ->sum('amount');

        $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
        return round($total / $daysInMonth, 2);
    }

    /**
     * Calculate percentage change between two values
     */
    private function calcChange(float $current, float $previous): array
    {
        if ($previous == 0) {
            return ['amount' => $current, 'percentage' => null, 'direction' => 'new'];
        }
        $pct = (($current - $previous) / $previous) * 100;
        return [
            'amount'     => round($current - $previous, 2),
            'percentage' => round($pct, 2),
            'direction'  => $pct >= 0 ? 'up' : 'down',
        ];
    }
}
