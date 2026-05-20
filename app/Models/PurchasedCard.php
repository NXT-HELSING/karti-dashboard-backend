<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchasedCard extends Model
{
    protected $fillable = [
        'transaction_id', 'card_code', 'serial',
        'face_value', 'currency', 'expiry_date'
    ];

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }
}
