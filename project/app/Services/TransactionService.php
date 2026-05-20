<?php
// app/Services/TransactionService.php

namespace App\Services;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * TransactionService
 * Core financial transaction business logic
 */
class TransactionService
{
    public function __construct(
        private readonly BudgetService $budgetService
    ) {}

    /**
     * Retrieve paginated transaction list with filters
     *
     * @param  User  $user
     * @param  array $filters  {type, category_id, from_date, to_date, month, year, search, per_page, sort_by, sort_dir}
     */
    public function getTransactions(User $user, array $filters = []): LengthAwarePaginator
    {
        $query = Transaction::with('category')
            ->where('user_id', $user->id);

        // ── Apply Filters ────────────────────────────────────────────────

        if (!empty($filters['type'])) {
            $query->ofType($filters['type']);
        }

        if (!empty($filters['category_id'])) {
            $query->inCategory((int) $filters['category_id']);
        }

        if (!empty($filters['from_date']) && !empty($filters['to_date'])) {
            $query->inDateRange($filters['from_date'], $filters['to_date']);
        } elseif (!empty($filters['month']) && !empty($filters['year'])) {
            $query->forMonth((int) $filters['month'], (int) $filters['year']);
        }

        if (!empty($filters['search'])) {
            $query->search($filters['search']);
        }

        if (!empty($filters['min_amount'])) {
            $query->where('amount', '>=', (float) $filters['min_amount']);
        }

        if (!empty($filters['max_amount'])) {
            $query->where('amount', '<=', (float) $filters['max_amount']);
        }

        // ── Sorting ──────────────────────────────────────────────────────

        $sortBy  = in_array($filters['sort_by'] ?? '', ['amount', 'description', 'transaction_date', 'created_at'])
                   ? $filters['sort_by']
                   : 'transaction_date';
        $sortDir = ($filters['sort_dir'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sortBy, $sortDir);

        $perPage = min((int) ($filters['per_page'] ?? 15), 100); // cap at 100

        return $query->paginate($perPage);
    }

    /**
     * Create a new transaction and trigger budget checks
     *
     * @param  User  $user
     * @param  array $data  Validated transaction data
     * @return array        Transaction + budget alerts
     */
    public function createTransaction(User $user, array $data): array
    {
        $transaction = Transaction::create([
            'user_id'            => $user->id,
            'category_id'        => $data['category_id'],
            'type'               => $data['type'],
            'amount'             => $data['amount'],
            'description'        => $data['description'],
            'notes'              => $data['notes'] ?? null,
            'transaction_date'   => $data['transaction_date'],
            'reference_number'   => $data['reference_number'] ?? null,
            'is_recurring'       => $data['is_recurring'] ?? false,
            'recurring_interval' => $data['recurring_interval'] ?? null,
            'tags'               => $data['tags'] ?? null,
        ]);

        $transaction->load('category');

        // Check budget status after adding expense
        $alerts = [];
        if ($data['type'] === 'expense') {
            $alerts = $this->budgetService->checkBudgetAlerts($user, $transaction);
        }

        return [
            'transaction' => $this->formatTransaction($transaction),
            'alerts'      => $alerts,
        ];
    }

    /**
     * Update an existing transaction
     *
     * @param  User        $user
     * @param  Transaction $transaction
     * @param  array       $data
     * @return array
     */
    public function updateTransaction(User $user, Transaction $transaction, array $data): array
    {
        $transaction->update([
            'category_id'        => $data['category_id'] ?? $transaction->category_id,
            'type'               => $data['type'] ?? $transaction->type,
            'amount'             => $data['amount'] ?? $transaction->amount,
            'description'        => $data['description'] ?? $transaction->description,
            'notes'              => $data['notes'] ?? $transaction->notes,
            'transaction_date'   => $data['transaction_date'] ?? $transaction->transaction_date,
            'reference_number'   => $data['reference_number'] ?? $transaction->reference_number,
            'is_recurring'       => $data['is_recurring'] ?? $transaction->is_recurring,
            'recurring_interval' => $data['recurring_interval'] ?? $transaction->recurring_interval,
            'tags'               => $data['tags'] ?? $transaction->tags,
        ]);

        $transaction->load('category');

        $alerts = [];
        if ($transaction->type === 'expense') {
            $alerts = $this->budgetService->checkBudgetAlerts($user, $transaction);
        }

        return [
            'transaction' => $this->formatTransaction($transaction),
            'alerts'      => $alerts,
        ];
    }

    /**
     * Soft-delete a transaction
     */
    public function deleteTransaction(Transaction $transaction): void
    {
        $transaction->delete();
    }

    /**
     * Calculate financial summary for a user (optionally filtered by period)
     *
     * @param  User   $user
     * @param  int    $month  0 = all time
     * @param  int    $year
     * @return array
     */
    public function getSummary(User $user, int $month = 0, int $year = 0): array
    {
        $query = Transaction::where('user_id', $user->id);

        if ($month > 0 && $year > 0) {
            $query->forMonth($month, $year);
        }

        $totals = $query->select(
            DB::raw("SUM(CASE WHEN type = 'income'  THEN amount ELSE 0 END) as total_income"),
            DB::raw("SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END) as total_expense"),
            DB::raw('COUNT(*) as transaction_count'),
            DB::raw("COUNT(CASE WHEN type = 'income'  THEN 1 END) as income_count"),
            DB::raw("COUNT(CASE WHEN type = 'expense' THEN 1 END) as expense_count"),
        )->first();

        $totalIncome  = (float) ($totals->total_income  ?? 0);
        $totalExpense = (float) ($totals->total_expense ?? 0);
        $balance      = $totalIncome - $totalExpense;
        $savingsRate  = $totalIncome > 0
                        ? round(($balance / $totalIncome) * 100, 2)
                        : 0;

        return [
            'total_income'      => $totalIncome,
            'total_expense'     => $totalExpense,
            'balance'           => $balance,
            'savings_rate'      => $savingsRate,
            'transaction_count' => (int) $totals->transaction_count,
            'income_count'      => (int) $totals->income_count,
            'expense_count'     => (int) $totals->expense_count,
            'period'            => $month > 0 ? ['month' => $month, 'year' => $year] : 'all_time',
        ];
    }

    /**
     * Format a transaction for API output
     */
    public function formatTransaction(Transaction $transaction): array
    {
        return [
            'id'                 => $transaction->id,
            'type'               => $transaction->type,
            'amount'             => (float) $transaction->amount,
            'description'        => $transaction->description,
            'notes'              => $transaction->notes,
            'transaction_date'   => $transaction->transaction_date->toDateString(),
            'reference_number'   => $transaction->reference_number,
            'is_recurring'       => $transaction->is_recurring,
            'recurring_interval' => $transaction->recurring_interval,
            'tags'               => $transaction->tags ?? [],
            'category'           => $transaction->category ? [
                'id'    => $transaction->category->id,
                'name'  => $transaction->category->name,
                'icon'  => $transaction->category->icon,
                'color' => $transaction->category->color,
                'type'  => $transaction->category->type,
            ] : null,
            'created_at'         => $transaction->created_at->toISOString(),
            'updated_at'         => $transaction->updated_at->toISOString(),
        ];
    }
}
