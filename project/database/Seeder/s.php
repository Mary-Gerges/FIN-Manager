<?php
// database/seeders/CategorySeeder.php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

/**
 * Seeds default system categories available to all users
 */
class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            // ── Expense Categories ──────────────────────────────────────
            [
                'name'      => 'Food & Dining',
                'type'      => 'expense',
                'icon'      => 'utensils',
                'color'     => '#ef4444',
                'is_system' => true,
            ],
            [
                'name'      => 'Transportation',
                'type'      => 'expense',
                'icon'      => 'car',
                'color'     => '#3b82f6',
                'is_system' => true,
            ],
            [
                'name'      => 'Bills & Utilities',
                'type'      => 'expense',
                'icon'      => 'zap',
                'color'     => '#f59e0b',
                'is_system' => true,
            ],
            [
                'name'      => 'Shopping',
                'type'      => 'expense',
                'icon'      => 'shopping-bag',
                'color'     => '#a855f7',
                'is_system' => true,
            ],
            [
                'name'      => 'Healthcare',
                'type'      => 'expense',
                'icon'      => 'heart-pulse',
                'color'     => '#10b981',
                'is_system' => true,
            ],
            [
                'name'      => 'Entertainment',
                'type'      => 'expense',
                'icon'      => 'film',
                'color'     => '#ec4899',
                'is_system' => true,
            ],
            [
                'name'      => 'Education',
                'type'      => 'expense',
                'icon'      => 'book-open',
                'color'     => '#6366f1',
                'is_system' => true,
            ],
            [
                'name'      => 'Housing',
                'type'      => 'expense',
                'icon'      => 'home',
                'color'     => '#0ea5e9',
                'is_system' => true,
            ],
            [
                'name'      => 'Personal Care',
                'type'      => 'expense',
                'icon'      => 'smile',
                'color'     => '#f97316',
                'is_system' => true,
            ],
            [
                'name'      => 'Travel',
                'type'      => 'expense',
                'icon'      => 'plane',
                'color'     => '#14b8a6',
                'is_system' => true,
            ],
            [
                'name'      => 'Other Expense',
                'type'      => 'expense',
                'icon'      => 'more-horizontal',
                'color'     => '#6b7280',
                'is_system' => true,
            ],

            // ── Income Categories ────────────────────────────────────────
            [
                'name'      => 'Salary',
                'type'      => 'income',
                'icon'      => 'briefcase',
                'color'     => '#10b981',
                'is_system' => true,
            ],
            [
                'name'      => 'Freelance',
                'type'      => 'income',
                'icon'      => 'laptop',
                'color'     => '#3b82f6',
                'is_system' => true,
            ],
            [
                'name'      => 'Investment Returns',
                'type'      => 'income',
                'icon'      => 'trending-up',
                'color'     => '#f59e0b',
                'is_system' => true,
            ],
            [
                'name'      => 'Business Income',
                'type'      => 'income',
                'icon'      => 'building',
                'color'     => '#6366f1',
                'is_system' => true,
            ],
            [
                'name'      => 'Other Income',
                'type'      => 'income',
                'icon'      => 'plus-circle',
                'color'     => '#6b7280',
                'is_system' => true,
            ],
        ];

        foreach ($categories as $category) {
            Category::firstOrCreate(
                ['name' => $category['name'], 'is_system' => true],
                $category
            );
        }

        $this->command->info('✅ System categories seeded successfully.');
    }
}
