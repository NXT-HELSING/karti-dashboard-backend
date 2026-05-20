<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerPurchase extends Model
{
    protected $table = 'customer_purchases';
    
    protected $fillable = [
        'user_id', 'transaction_id', 'denomination_id', 'card_code',
        'serial_number', 'face_value', 'currency', 'expiry_date',
        'status', 'provider_response', 'used_at'
    ];
    
    protected $casts = [
        'expiry_date' => 'date',
        'provider_response' => 'array',
        'used_at' => 'datetime',
    ];
    
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    
    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }
    
    public function denomination()
    {
        return $this->belongsTo(Denomination::class);
    }
}
