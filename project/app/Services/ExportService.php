<?php
// app/Services/ExportService.php

namespace App\Services;

use App\Models\Transaction;
use App\Models\User;

/**
 * ExportService
 * Generates CSV and PDF export files
 */
class ExportService
{
    /**
     * Generate CSV export for a given period
     *
     * @param  User $user
     * @param  int  $month  0 = all time
     * @param  int  $year
     * @return array  ['filename' => string, 'content' => string, 'mime' => string]
     */
    public function exportCSV(User $user, int $month = 0, int $year = 0): array
    {
        $query = Transaction::with('category')
            ->where('user_id', $user->id)
            ->orderBy('transaction_date');

        if ($month > 0 && $year > 0) {
            $query->forMonth($month, $year);
        }

        $transactions = $query->get();

        // ── Build CSV ────────────────────────────────────────────────────
        $rows = [];

        // Header row
        $rows[] = [
            'ID', 'Date', 'Type', 'Category', 'Description',
            'Amount', 'Currency', 'Notes', 'Reference', 'Tags', 'Created At'
        ];

        foreach ($transactions as $t) {
            $rows[] = [
                $t->id,
                $t->transaction_date->toDateString(),
                strtoupper($t->type),
                $t->category?->name ?? '',
                $t->description,
                number_format($t->amount, 2, '.', ''),
                $user->currency,
                $t->notes ?? '',
                $t->reference_number ?? '',
                implode(', ', $t->tags ?? []),
                $t->created_at->toDateTimeString(),
            ];
        }

        $output = fopen('php://temp', 'r+');
        foreach ($rows as $row) {
            fputcsv($output, $row);
        }
        rewind($output);
        $content = stream_get_contents($output);
        fclose($output);

        $periodLabel = $month > 0
            ? date('F_Y', mktime(0, 0, 0, $month, 1, $year))
            : 'All_Time';

        return [
            'filename' => "FinanceTracker_{$periodLabel}.csv",
            'content'  => $content,
            'mime'     => 'text/csv',
        ];
    }

    /**
     * Generate a plain-text financial report (PDF requires additional library)
     * Returns structured data that can be rendered by frontend or a PDF library
     *
     * @param  User $user
     * @param  int  $month
     * @param  int  $year
     * @return array
     */
    public function exportReportData(User $user, int $month, int $year): array
    {
        $transactions = Transaction::with('category')
            ->where('user_id', $user->id)
            ->forMonth($month, $year)
            ->orderBy('transaction_date')
            ->get();

        $income  = $transactions->where('type', 'income')->sum('amount');
        $expense = $transactions->where('type', 'expense')->sum('amount');

        return [
            'report_title'   => "Financial Report – " . date('F Y', mktime(0, 0, 0, $month, 1, $year)),
            'generated_at'   => now()->toDateTimeString(),
            'user'           => ['name' => $user->name, 'email' => $user->email, 'currency' => $user->currency],
            'summary'        => [
                'total_income'  => (float) $income,
                'total_expense' => (float) $expense,
                'net_balance'   => (float) ($income - $expense),
                'savings_rate'  => $income > 0 ? round((($income - $expense) / $income) * 100, 2) : 0,
            ],
            'transactions'   => $transactions->map(fn($t) => [
                'date'        => $t->transaction_date->toDateString(),
                'type'        => $t->type,
                'category'    => $t->category?->name ?? 'N/A',
                'description' => $t->description,
                'amount'      => (float) $t->amount,
            ])->toArray(),
        ];
    }
}
