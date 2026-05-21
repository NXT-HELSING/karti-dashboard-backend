<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'is_admin',
        'is_active',
        'balance',
        'phone',
        'role',
        'total_paid',
        'total_spent',
        'credit_balance',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'is_admin' => 'boolean',
        'is_active' => 'boolean',
        'balance' => 'decimal:2',
        'total_paid' => 'decimal:2',
        'total_spent' => 'decimal:2',
        'credit_balance' => 'decimal:2',
    ];

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    public function balanceHistory()
    {
        return $this->hasMany(BalanceHistory::class);
    }
}
