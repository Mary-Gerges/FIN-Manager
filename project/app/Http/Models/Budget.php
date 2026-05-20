<?php
// app/Models/Budget.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Budget Model
 * Monthly spending limits per category
 */
class Budget extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'category_id',
        'amount',
        'month',
        'year',
        'alert_threshold',
        'is_active',
    ];

    protected $casts = [
        'amount'          => 'decimal:2',
        'alert_threshold' => 'decimal:2',
        'month'           => 'integer',
        'year'            => 'integer',
        'is_active'       => 'boolean',
    ];

    // ── Relationships ────────────────────────────────────────────────────

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    // ── Scopes ──────────────────────────────────────────────────────────

    public function scopeForMonth($query, int $month, int $year)
    {
        return $query->where('month', $month)->where('year', $year);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
