<?php
// database/migrations/2024_01_01_000003_create_transactions_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration: Create transactions table
 * Core financial data — income and expense records
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                  ->constrained('users')
                  ->onDelete('cascade');
            $table->foreignId('category_id')
                  ->constrained('categories')
                  ->onDelete('restrict');                         // prevent orphaned transactions
            $table->string('type', 10);                          // 'income' | 'expense'
            $table->decimal('amount', 15, 2);                    // supports large amounts
            $table->string('description', 255);
            $table->text('notes')->nullable();
            $table->date('transaction_date');
            $table->string('reference_number', 100)->nullable(); // bank reference, receipt number
            $table->boolean('is_recurring')->default(false);
            $table->string('recurring_interval', 20)->nullable(); // 'daily'|'weekly'|'monthly'|'yearly'
            $table->json('tags')->nullable();                    // flexible tagging
            $table->timestamps();
            $table->softDeletes();

            // Performance indexes for common query patterns
            $table->index(['user_id', 'transaction_date']);
            $table->index(['user_id', 'type']);
            $table->index(['user_id', 'category_id']);
            $table->index(['user_id', 'transaction_date', 'type']);
            $table->index('transaction_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
