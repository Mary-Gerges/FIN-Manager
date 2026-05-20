<?php
// app/Models/User.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * User Model
 *
 * @property int    $id
 * @property string $name
 * @property string $email
 * @property string $password
 * @property string $currency
 * @property string $timezone
 * @property bool   $budget_alerts
 * @property bool   $unusual_spending_alerts
 */
class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'password',
        'currency',
        'timezone',
        'monthly_income_goal',
        'budget_alerts',
        'unusual_spending_alerts',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at'        => 'datetime',
        'password'                 => 'hashed',      // auto-bcrypt in Laravel 10+
        'budget_alerts'            => 'boolean',
        'unusual_spending_alerts'  => 'boolean',
        'monthly_income_goal'      => 'decimal:2',
    ];

    // ── Relationships ────────────────────────────────────────────────────

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    public function budgets()
    {
        return $this->hasMany(Budget::class);
    }

    public function categories()
    {
        return $this->hasMany(Category::class);
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }

    // ── Accessors ────────────────────────────────────────────────────────

    /**
     * Get all categories available to this user (system + personal)
     */
    public function availableCategories()
    {
        return Category::where('is_system', true)
            ->orWhere('user_id', $this->id)
            ->where('is_active', true);
    }
}
