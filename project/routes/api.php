<?php
// routes/api.php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BudgetController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\InsightController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\TransactionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Finance Tracker API Routes
|--------------------------------------------------------------------------
|
| All routes are prefixed with /api (set in bootstrap/app.php or RouteServiceProvider)
| Protected routes require: Authorization: Bearer {token}
|
*/

// ── Public Routes (no auth required) ─────────────────────────────────────
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login',    [AuthController::class, 'login']);
});

// ── Protected Routes (require Sanctum token) ──────────────────────────────
Route::middleware(['auth:sanctum'])->group(function () {

    // Auth & Profile
    Route::prefix('auth')->group(function () {
        Route::post('/logout',          [AuthController::class, 'logout']);
        Route::get('/me',               [AuthController::class, 'me']);
        Route::put('/profile',          [AuthController::class, 'updateProfile']);
        Route::put('/password',         [AuthController::class, 'changePassword']);
    });

    // Dashboard (aggregated data)
    Route::get('/dashboard',            [DashboardController::class, 'index']);
    Route::get('/notifications',        [DashboardController::class, 'notifications']);

    // Transactions
    Route::prefix('transactions')->group(function () {
        Route::get('/',                 [TransactionController::class, 'index']);
        Route::post('/',                [TransactionController::class, 'store']);
        Route::get('/summary',          [TransactionController::class, 'summary']);
        Route::delete('/bulk',          [TransactionController::class, 'bulkDelete']);
        Route::get('/{id}',             [TransactionController::class, 'show']);
        Route::put('/{id}',             [TransactionController::class, 'update']);
        Route::delete('/{id}',          [TransactionController::class, 'destroy']);
    });

    // Categories
    Route::apiResource('categories', CategoryController::class)
         ->except(['show']); // show not needed

    // Budgets
    Route::prefix('budgets')->group(function () {
        Route::get('/',                 [BudgetController::class, 'index']);
        Route::post('/',                [BudgetController::class, 'store']);
        Route::get('/alerts',           [BudgetController::class, 'alerts']);
        Route::get('/{id}',             [BudgetController::class, 'show']);
        Route::delete('/{id}',          [BudgetController::class, 'destroy']);
    });

    // Reports & Charts
    Route::prefix('reports')->group(function () {
        Route::get('/monthly',                  [ReportController::class, 'monthly']);
        Route::get('/chart/income-vs-expense',  [ReportController::class, 'incomeVsExpenseChart']);
        Route::get('/chart/expense-by-category',[ReportController::class, 'expenseByCategoryChart']);
        Route::get('/chart/daily',              [ReportController::class, 'dailyChart']);
        Route::get('/export/csv',               [ReportController::class, 'exportCSV']);
        Route::get('/export/json',              [ReportController::class, 'exportJSON']);
    });

    // AI Insights
    Route::get('/insights',             [InsightController::class, 'index']);
});
