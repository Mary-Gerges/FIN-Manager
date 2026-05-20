<?php
// app/Models/Transaction.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Transaction Model
 *
 * @property int    $id
 * @property int    $user_id
 * @property int    $category_id
 * @property string $type        income|expense
 * @property float  $amount
 * @property string $description
 * @property string $notes
 * @property string $transaction_date
 */
class Transaction extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'category_id',
        'type',
        'amount',
        'description',
        'notes',
        'transaction_date',
        'reference_number',
        'is_recurring',
        'recurring_interval',
        'tags',
    ];

    protected $casts = [
        'amount'           => 'decimal:2',
        'transaction_date' => 'date',
        'is_recurring'     => 'boolean',
        'tags'             => 'array',
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

    /** Filter by transaction type */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /** Filter by date range */
    public function scopeInDateRange($query, string $from, string $to)
    {
        return $query->whereBetween('transaction_date', [$from, $to]);
    }

    /** Filter for a specific month/year */
    public function scopeForMonth($query, int $month, int $year)
    {
        return $query->whereMonth('transaction_date', $month)
                     ->whereYear('transaction_date', $year);
    }

    /** Filter by category */
    public function scopeInCategory($query, int $categoryId)
    {
        return $query->where('category_id', $categoryId);
    }

    /** Search in description or notes */
    public function scopeSearch($query, string $term)
    {
        return $query->where(function ($q) use ($term) {
            $q->where('description', 'LIKE', "%{$term}%")
              ->orWhere('notes', 'LIKE', "%{$term}%")
              ->orWhere('reference_number', 'LIKE', "%{$term}%");
        });
    }
}
