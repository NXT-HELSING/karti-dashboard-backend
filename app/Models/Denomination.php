<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Denomination extends Model
{
    protected $table = 'denominations';
    
    protected $fillable = [
        'brand_id', 'provider_denom_id', 'name', 'value', 
        'price', 'currency', 'description', 'image_url', 
        'is_available', 'stock_quantity'
    ];
    
    protected $casts = [
        'is_available' => 'boolean',
        'price' => 'decimal:2'
    ];
    
    public function brand()
    {
        return $this->belongsTo(Brand::class);
    }
    
    public function purchases()
    {
        return $this->hasMany(CustomerPurchase::class);
    }
}
