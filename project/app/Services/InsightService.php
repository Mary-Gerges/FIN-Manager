<?php
// app/Services/InsightService.php

namespace App\Services;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * InsightService
 * Rule-based AI spending analysis and recommendations
 */
class InsightService
{
    /** Thresholds for anomaly detection */
    private const SPIKE_THRESHOLD       = 1.5;   // 50% above average = spike
    private const HIGH_FOOD_PCT         = 0.35;   // >35% on food = high
    private const LOW_SAVINGS_RATE      = 0.10;   // <10% savings = low
    private const IDEAL_SAVINGS_RATE    = 0.20;   // 20% savings target
    private const FREQUENT_SMALL_LIMIT  = 10;     // >10 small transactions = frequent

    /**
     * Generate comprehensive financial insights for a user
     *
     * @param  User $user
     * @return array
     */
    public function generateInsights(User $user): array
    {
        $now   = now();
        $month = (int) $now->format('m');
        $year  = (int) $now->format('Y');

        $insights = [];

        // Run all analysis checks
        $insights = array_merge(
            $insights,
            $this->analyzeSavingsRate($user, $month, $year),
            $this->analyzeSpendingSpike($user, $month, $year),
            $this->analyzeCategoryDistribution($user, $month, $year),
            $this->analyzeFrequentSmallPurchases($user, $month, $year),
            $this->analyzeRecurringExpenses($user),
            $this->analyzeWeekendSpending($user, $month, $year),
            $this->detectUnusualTransactions($user, $month, $year)
        );

        // Sort by severity: danger > warning > info > success
        $order = ['danger' => 0, 'warning' => 1, 'info' => 2, 'success' => 3];
        usort($insights, fn($a, $b) => ($order[$a['severity']] ?? 4) - ($order[$b['severity']] ?? 4));

        return [
            'insights'        => $insights,
            'generated_at'    => $now->toISOString(),
            'period'          => ['month' => $month, 'year' => $year],
            'total_insights'  => count($insights),
        ];
    }

    /**
     * Analyze savings rate and provide recommendations
     */
    private function analyzeSavingsRate(User $user, int $month, int $year): array
    {
        $income  = (float) Transaction::where('user_id', $user->id)
            ->where('type', 'income')->forMonth($month, $year)->sum('amount');
        $expense = (float) Transaction::where('user_id', $user->id)
            ->where('type', 'expense')->forMonth($month, $year)->sum('amount');

        if ($income <= 0) {
            return [[
                'id'          => 'no_income',
                'type'        => 'no_income',
                'severity'    => 'warning',
                'icon'        => 'alert-circle',
                'title'       => 'No Income Recorded',
                'description' => 'No income transactions found for this month. Add your income to get accurate financial insights.',
                'action'      => 'Add your salary or other income sources',
                'data'        => [],
            ]];
        }

        $savingsRate = ($income - $expense) / $income;
        $insights    = [];

        if ($savingsRate < 0) {
            $insights[] = [
                'id'          => 'negative_balance',
                'type'        => 'savings_rate',
                'severity'    => 'danger',
                'icon'        => 'trending-down',
                'title'       => 'Spending Exceeds Income',
                'description' => sprintf(
                    'You are spending %.2f more than you earn this month. This is unsustainable.',
                    abs($expense - $income)
                ),
                'action'      => 'Review and cut non-essential expenses immediately',
                'data'        => ['savings_rate' => round($savingsRate * 100, 2)],
            ];
        } elseif ($savingsRate < self::LOW_SAVINGS_RATE) {
            $insights[] = [
                'id'          => 'low_savings',
                'type'        => 'savings_rate',
                'severity'    => 'warning',
                'icon'        => 'piggy-bank',
                'title'       => 'Low Savings Rate',
                'description' => sprintf(
                    'Your savings rate is %.1f%%. Experts recommend saving at least 20%% of income.',
                    $savingsRate * 100
                ),
                'action'      => sprintf(
                    'Try to save an additional %.2f this month to reach the 20%% target.',
                    ($income * self::IDEAL_SAVINGS_RATE) - ($income - $expense)
                ),
                'data'        => [
                    'current_rate' => round($savingsRate * 100, 2),
                    'target_rate'  => self::IDEAL_SAVINGS_RATE * 100,
                    'gap'          => round(($income * self::IDEAL_SAVINGS_RATE) - ($income - $expense), 2),
                ],
            ];
        } elseif ($savingsRate >= 0.30) {
            $insights[] = [
                'id'          => 'excellent_savings',
                'type'        => 'savings_rate',
                'severity'    => 'success',
                'icon'        => 'award',
                'title'       => 'Excellent Savings Rate!',
                'description' => sprintf(
                    'You are saving %.1f%% of your income. Consider investing the surplus for long-term growth.',
                    $savingsRate * 100
                ),
                'action'      => 'Explore investment options: index funds, bonds, or real estate',
                'data'        => ['savings_rate' => round($savingsRate * 100, 2)],
            ];
        }

        return $insights;
    }

    /**
     * Detect spending spikes compared to previous months
     */
    private function analyzeSpendingSpike(User $user, int $month, int $year): array
    {
        // Get average expense for last 3 months (excluding current)
        $prevMonths = [];
        for ($i = 1; $i <= 3; $i++) {
            $d = \Carbon\Carbon::createFromDate($year, $month, 1)->subMonths($i);
            $prevMonths[] = (float) Transaction::where('user_id', $user->id)
                ->where('type', 'expense')
                ->forMonth((int) $d->format('m'), (int) $d->format('Y'))
                ->sum('amount');
        }

        $avgPrev = array_sum($prevMonths) / count($prevMonths);
        if ($avgPrev <= 0) return [];

        $currentExpense = (float) Transaction::where('user_id', $user->id)
            ->where('type', 'expense')
            ->forMonth($month, $year)
            ->sum('amount');

        if ($currentExpense > $avgPrev * self::SPIKE_THRESHOLD) {
            $increase = $currentExpense - $avgPrev;
            return [[
                'id'          => 'spending_spike',
                'type'        => 'unusual_spending',
                'severity'    => 'warning',
                'icon'        => 'trending-up',
                'title'       => 'Unusual Spending Spike Detected',
                'description' => sprintf(
                    'Your spending this month (%.2f) is %.1f%% higher than your 3-month average (%.2f).',
                    $currentExpense,
                    (($currentExpense - $avgPrev) / $avgPrev) * 100,
                    $avgPrev
                ),
                'action'      => 'Review recent transactions to identify and reduce unnecessary spending',
                'data'        => [
                    'current_expense'  => $currentExpense,
                    'average_expense'  => round($avgPrev, 2),
                    'increase_amount'  => round($increase, 2),
                    'increase_percent' => round((($currentExpense - $avgPrev) / $avgPrev) * 100, 2),
                ],
            ]];
        }

        return [];
    }

    /**
     * Analyze category spending distribution
     */
    private function analyzeCategoryDistribution(User $user, int $month, int $year): array
    {
        $total = (float) Transaction::where('user_id', $user->id)
            ->where('type', 'expense')->forMonth($month, $year)->sum('amount');

        if ($total <= 0) return [];

        $byCategory = Transaction::with('category')
            ->where('user_id', $user->id)
            ->where('type', 'expense')
            ->forMonth($month, $year)
            ->select('category_id', DB::raw('SUM(amount) as cat_total'))
            ->groupBy('category_id')
            ->orderByDesc('cat_total')
            ->get();

        $insights = [];
        foreach ($byCategory as $row) {
            $pct = $row->cat_total / $total;

            // Flag food spending > 35%
            if (stripos($row->category?->name ?? '', 'food') !== false && $pct > self::HIGH_FOOD_PCT) {
                $insights[] = [
                    'id'          => 'high_food_spending',
                    'type'        => 'category_analysis',
                    'severity'    => 'info',
                    'icon'        => 'utensils',
                    'title'       => 'High Food & Dining Spending',
                    'description' => sprintf(
                        'Food & Dining accounts for %.1f%% of your expenses (%.2f). Cooking at home can reduce this significantly.',
                        $pct * 100,
                        $row->cat_total
                    ),
                    'action'      => 'Try meal prepping 3 days a week — could save 30-40% on food costs',
                    'data'        => ['percentage' => round($pct * 100, 2), 'amount' => (float) $row->cat_total],
                ];
            }

            // Flag any single category > 50%
            if ($pct > 0.50 && stripos($row->category?->name ?? '', 'housing') === false
                            && stripos($row->category?->name ?? '', 'rent') === false) {
                $insights[] = [
                    'id'          => "dominant_category_{$row->category_id}",
                    'type'        => 'category_analysis',
                    'severity'    => 'info',
                    'icon'        => 'pie-chart',
                    'title'       => "Dominant Spending: {$row->category?->name}",
                    'description' => sprintf(
                        '%s takes up %.1f%% of your total expenses. Consider setting a budget limit.',
                        $row->category?->name ?? 'This category',
                        $pct * 100
                    ),
                    'action'      => "Set a monthly budget for {$row->category?->name} to control spending",
                    'data'        => ['percentage' => round($pct * 100, 2), 'amount' => (float) $row->cat_total],
                ];
            }
        }

        return $insights;
    }

    /**
     * Detect frequent small purchases (death by a thousand cuts)
     */
    private function analyzeFrequentSmallPurchases(User $user, int $month, int $year): array
    {
        $avgExpense = Transaction::where('user_id', $user->id)
            ->where('type', 'expense')->avg('amount') ?? 0;

        $smallThreshold = max(10, $avgExpense * 0.10); // 10% of average or \$10

        $smallCount = Transaction::where('user_id', $user->id)
            ->where('type', 'expense')
            ->where('amount', '<', $smallThreshold)
            ->forMonth($month, $year)
            ->count();

        $smallTotal = (float) Transaction::where('user_id', $user->id)
            ->where('type', 'expense')
            ->where('amount', '<', $smallThreshold)
            ->forMonth($month, $year)
            ->sum('amount');

        if ($smallCount > self::FREQUENT_SMALL_LIMIT) {
            return [[
                'id'          => 'frequent_small_purchases',
                'type'        => 'spending_pattern',
                'severity'    => 'info',
                'icon'        => 'coffee',
                'title'       => 'Many Small Purchases Add Up',
                'description' => sprintf(
                    'You made %d small purchases (under %.2f each) totaling %.2f this month.',
                    $smallCount,
                    $smallThreshold,
                    $smallTotal
                ),
                'action'      => 'Track these "micro-expenses" — they often add up to significant amounts',
                'data'        => [
                    'count'     => $smallCount,
                    'total'     => $smallTotal,
                    'threshold' => $smallThreshold,
                ],
            ]];
        }

        return [];
    }

    /**
     * Highlight recurring expenses for review
     */
    private function analyzeRecurringExpenses(User $user): array
    {
        $recurring = Transaction::where('user_id', $user->id)
            ->where('type', 'expense')
            ->where('is_recurring', true)
            ->sum('amount');

        if ($recurring > 0) {
            return [[
                'id'          => 'recurring_expenses',
                'type'        => 'recurring',
                'severity'    => 'info',
                'icon'        => 'repeat',
                'title'       => 'Recurring Expenses Overview',
                'description' => sprintf(
                    'You have %.2f in recurring monthly expenses. Review subscriptions regularly.',
                    $recurring
                ),
                'action'      => 'Audit subscriptions — cancel any you haven\'t used in 30+ days',
                'data'        => ['total_recurring' => (float) $recurring],
            ]];
        }

        return [];
    }

    /**
     * Analyze weekend vs weekday spending patterns
     */
    private function analyzeWeekendSpending(User $user, int $month, int $year): array
    {
        $transactions = Transaction::where('user_id', $user->id)
            ->where('type', 'expense')
            ->forMonth($month, $year)
            ->get();

        $weekdayTotal  = 0;
        $weekendTotal  = 0;
        $weekdayDays   = 0;
        $weekendDays   = 0;

        foreach ($transactions as $t) {
            $dow = (int) $t->transaction_date->format('N'); // 1=Mon, 7=Sun
            if ($dow >= 6) {
                $weekendTotal += $t->amount;
                $weekendDays++;
            } else {
                $weekdayTotal += $t->amount;
                $weekdayDays++;
            }
        }

        if ($weekendDays > 0 && $weekdayDays > 0) {
            $weekdayAvg = $weekdayTotal / $weekdayDays;
            $weekendAvg = $weekendTotal / $weekendDays;

            if ($weekendAvg > $weekdayAvg * 2) {
                return [[
                    'id'          => 'weekend_spending',
                    'type'        => 'spending_pattern',
                    'severity'    => 'info',
                    'icon'        => 'calendar',
                    'title'       => 'Higher Weekend Spending',
                    'description' => sprintf(
                        'Your average weekend spending (%.2f/transaction) is %.1fx your weekday average (%.2f/transaction).',
                        $weekendAvg,
                        $weekdayAvg > 0 ? $weekendAvg / $weekdayAvg : 0,
                        $weekdayAvg
                    ),
                    'action'      => 'Plan weekend activities in advance to reduce impulse spending',
                    'data'        => [
                        'weekend_avg' => round($weekendAvg, 2),
                        'weekday_avg' => round($weekdayAvg, 2),
                    ],
                ]];
            }
        }

        return [];
    }

    /**
     * Detect individual transactions that are unusually large
     */
    private function detectUnusualTransactions(User $user, int $month, int $year): array
    {
        // Calculate the user's historical average expense
        $stats = Transaction::where('user_id', $user->id)
            ->where('type', 'expense')
            ->select(DB::raw('AVG(amount) as avg_amount'), DB::raw('STDDEV(amount) as std_amount'))
            ->first();

        if (!$stats || !$stats->avg_amount) return [];

        $threshold = $stats->avg_amount + (2 * ($stats->std_amount ?? $stats->avg_amount));

        $unusual = Transaction::with('category')
            ->where('user_id', $user->id)
            ->where('type', 'expense')
            ->where('amount', '>', $threshold)
            ->forMonth($month, $year)
            ->orderByDesc('amount')
            ->limit(3)
            ->get();

        if ($unusual->isEmpty()) return [];

        return [[
            'id'          => 'unusual_transactions',
            'type'        => 'unusual_spending',
            'severity'    => 'warning',
            'icon'        => 'alert-triangle',
            'title'       => 'Unusually Large Transactions',
            'description' => sprintf(
                'Found %d transaction(s) significantly above your typical spending pattern.',
                $unusual->count()
            ),
            'action'      => 'Review these transactions to ensure they are expected',
            'data'        => [
                'transactions' => $unusual->map(fn($t) => [
                    'id'          => $t->id,
                    'description' => $t->description,
                    'amount'      => (float) $t->amount,
                    'date'        => $t->transaction_date->toDateString(),
                    'category'    => $t->category?->name,
                ])->toArray(),
                'threshold' => round($threshold, 2),
            ],
        ]];
    }
}
