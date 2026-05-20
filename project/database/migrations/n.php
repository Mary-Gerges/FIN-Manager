<?php
// database/migrations/2024_01_01_000002_create_categories_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration: Create categories table
 * Supports both system-default and user-custom categories
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                  ->nullable()                                   // null = system/global category
                  ->constrained('users')
                  ->onDelete('cascade');
            $table->string('name', 100);
            $table->string('type', 10);                          // 'income' | 'expense' | 'both'
            $table->string('icon', 50)->default('tag');          // icon identifier
            $table->string('color', 7)->default('#6366f1');      // hex color
            $table->boolean('is_system')->default(false);        // system categories cannot be deleted
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Compound index for user-specific category lookup
            $table->index(['user_id', 'type', 'is_active']);
            $table->index('is_system');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
