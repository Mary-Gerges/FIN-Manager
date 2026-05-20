<?php
// database/migrations/2024_01_01_000004_create_budgets_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration: Create budgets table
 * Monthly budget limits per category per user
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                  ->constrained('users')
                  ->onDelete('cascade');
            $table->foreignId('category_id')
                  ->constrained('categories')
                  ->onDelete('cascade');
            $table->decimal('amount', 15, 2);                    // budget limit
            $table->unsignedTinyInteger('month');                 // 1-12
            $table->unsignedSmallInteger('year');
            $table->decimal('alert_threshold', 5, 2)->default(80.00); // alert at 80%
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // One budget per category per month per user
            $table->unique(['user_id', 'category_id', 'month', 'year'], 'unique_budget');
            $table->index(['user_id', 'month', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budgets');
    }
};
