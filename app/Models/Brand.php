<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Brand extends Model
{
    protected $fillable = ['name', 'code', 'logo_url', 'description', 'is_active', 'sort_order', 'api_config'];
    
    protected $casts = [
        'api_config' => 'array',
        'is_active' => 'boolean',
    ];
    
    public function denominations()
    {
        return $this->hasMany(Denomination::class);
    }
    
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
    
    public function getApiBrandId()
    {
        if (is_array($this->api_config) && isset($this->api_config['brand_id'])) {
            return (int)$this->api_config['brand_id'];
        }
        return (int)$this->id;
    }
}
