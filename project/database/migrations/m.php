<?php
// database/migrations/2024_01_01_000001_create_users_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration: Create users table
 * Stores user accounts with authentication data and preferences
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('email', 150)->unique();
            $table->string('password');                          // bcrypt hashed
            $table->string('currency', 10)->default('USD');
            $table->string('timezone', 50)->default('UTC');
            $table->decimal('monthly_income_goal', 15, 2)->nullable();
            $table->boolean('budget_alerts')->default(true);
            $table->boolean('unusual_spending_alerts')->default(true);
            $table->timestamp('email_verified_at')->nullable();
            $table->string('remember_token', 100)->nullable();
            $table->timestamps();
            $table->softDeletes();                               // safe deletion

            // Indexes for performance
            $table->index('email');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
