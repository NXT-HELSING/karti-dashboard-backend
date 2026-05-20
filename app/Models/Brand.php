<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Brand extends Model
{
    protected $fillable = ['name', 'code', 'logo_url', 'description', 'is_active', 'api_config'];
    
    protected $casts = [
        'api_config' => 'array',
        'is_active' => 'boolean',
    ];
    
    public function denominations()
    {
        return $this->hasMany(Denomination::class);
    }
    
    public function purchases()
    {
        return $this->hasManyThrough(CustomerPurchase::class, Denomination::class);
    }
}
