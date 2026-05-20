<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BalanceHistory extends Model
{
    protected $table = 'balance_history';
    
    protected $fillable = [
        'user_id', 'amount', 'currency', 'type', 'description', 'transaction_id'
    ];
    
    protected $casts = [
        'amount' => 'decimal:2',
    ];
    
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    
    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }
}
