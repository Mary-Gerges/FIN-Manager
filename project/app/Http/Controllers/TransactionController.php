<?php
// app/Http/Controllers/Api/TransactionController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Transaction\StoreTransactionRequest;
use App\Models\Transaction;
use App\Services\TransactionService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * TransactionController
 * Full CRUD for financial transactions with filtering, pagination, and summary
 */
class TransactionController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly TransactionService $transactionService
    ) {}

    /**
     * GET /api/transactions
     * List transactions with filters and pagination
     *
     * Query params: type, category_id, from_date, to_date, month, year,
     *               search, per_page, sort_by, sort_dir, min_amount, max_amount
     */
    public function index(Request $request): JsonResponse
    {
        $paginator = $this->transactionService->getTransactions(
            $request->user(),
            $request->only([
                'type', 'category_id', 'from_date', 'to_date',
                'month', 'year', 'search', 'per_page', 'sort_by',
                'sort_dir', 'min_amount', 'max_amount',
            ])
        );

        // Transform items for response
        $items = collect($paginator->items())
            ->map(fn($t) => $this->transactionService->formatTransaction($t))
            ->values();

        return $this->paginatedResponse(
            new class($paginator, $items) {
                public function __construct(private $p, private $i) {}
                public function items()        { return $this->i->toArray(); }
                public function currentPage()  { return $this->p->currentPage(); }
                public function perPage()      { return $this->p->perPage(); }
                public function total()        { return $this->p->total(); }
                public function lastPage()     { return $this->p->lastPage(); }
                public function firstItem()    { return $this->p->firstItem(); }
                public function lastItem()     { return $this->p->lastItem(); }
                public function hasMorePages() { return $this->p->hasMorePages(); }
            },
            'Transactions retrieved successfully'
        );
    }

    /**
     * POST /api/transactions
     * Create a new transaction
     */
    public function store(StoreTransactionRequest $request): JsonResponse
    {
        // Verify category belongs to user or is a system category
        $this->validateCategoryAccess($request->category_id, $request->user()->id);

        $result = $this->transactionService->createTransaction(
            $request->user(),
            $request->validated()
        );

        return $this->successResponse($result, 'Transaction created successfully', 201);
    }

    /**
     * GET /api/transactions/{id}
     * Get a single transaction
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $transaction = Transaction::with('category')
            ->where('user_id', $request->user()->id)
            ->find($id);

        if (!$transaction) {
            return $this->notFoundResponse('Transaction');
        }

        return $this->successResponse(
            $this->transactionService->formatTransaction($transaction),
            'Transaction retrieved successfully'
        );
    }

    /**
     * PUT /api/transactions/{id}
     * Update a transaction
     */
    public function update(StoreTransactionRequest $request, int $id): JsonResponse
    {
        $transaction = Transaction::where('user_id', $request->user()->id)->find($id);

        if (!$transaction) {
            return $this->notFoundResponse('Transaction');
        }

        $result = $this->transactionService->updateTransaction(
            $request->user(),
            $transaction,
            $request->validated()
        );

        return $this->successResponse($result, 'Transaction updated successfully');
    }

    /**
     * DELETE /api/transactions/{id}
     * Soft-delete a transaction
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $transaction = Transaction::where('user_id', $request->user()->id)->find($id);

        if (!$transaction) {
            return $this->notFoundResponse('Transaction');
        }

        $this->transactionService->deleteTransaction($transaction);

        return $this->successResponse(null, 'Transaction deleted successfully');
    }

    /**
     * GET /api/transactions/summary
     * Get financial summary (balance, income, expense totals)
     *
     * Query params: month, year (optional — defaults to all time)
     */
    public function summary(Request $request): JsonResponse
    {
        $month = (int) ($request->month ?? 0);
        $year  = (int) ($request->year ?? 0);

        $summary = $this->transactionService->getSummary(
            $request->user(),
            $month,
            $year
        );

        return $this->successResponse($summary, 'Summary retrieved successfully');
    }

    /**
     * GET /api/transactions/bulk-delete
     * DELETE /api/transactions/bulk
     * Delete multiple transactions
     */
    public function bulkDelete(Request $request): JsonResponse
    {
        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'ids'   => ['required', 'array', 'min:1', 'max:100'],
            'ids.*' => ['integer', 'min:1'],
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors()->toArray());
        }

        $deleted = Transaction::where('user_id', $request->user()->id)
            ->whereIn('id', $request->ids)
            ->delete();

        return $this->successResponse(
            ['deleted_count' => $deleted],
            "{$deleted} transaction(s) deleted successfully"
        );
    }

    /**
     * Verify the category is accessible by the user
     */
    private function validateCategoryAccess(int $categoryId, int $userId): void
    {
        $exists = \App\Models\Category::where('id', $categoryId)
            ->where(function ($q) use ($userId) {
                $q->where('is_system', true)->orWhere('user_id', $userId);
            })
            ->where('is_active', true)
            ->exists();

        if (!$exists) {
            abort(422, 'Invalid or inaccessible category');
        }
    }
}
