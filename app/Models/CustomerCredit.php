<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerCredit extends Model
{
    protected $fillable = [
        'user_id', 'amount', 'currency', 'type', 'payment_method',
        'reference', 'description', 'transaction_id', 'admin_id'
    ];
    
    protected $casts = [
        'amount' => 'decimal:2'
    ];
    
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
    
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }
    
    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }
}
