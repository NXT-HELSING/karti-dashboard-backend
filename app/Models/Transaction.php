<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Transaction extends Model
{
    protected $fillable = [
        'user_id', 'provider', 'denom_id', 'brand_id',
        'amount_paid', 'currency', 'status', 'reserve_id',
        'partner_transaction_id'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function purchasedCard(): HasOne
    {
        return $this->hasOne(PurchasedCard::class);
    }
}
