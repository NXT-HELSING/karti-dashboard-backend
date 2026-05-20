<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdminLog extends Model
{
    protected $table = 'admin_logs';
    
    protected $fillable = [
        'admin_id', 'action', 'entity_type', 'entity_id', 
        'old_values', 'new_values', 'ip_address'
    ];
    
    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
    ];
    
    public function admin()
    {
        return $this->belongsTo(User::class, 'admin_id');
    }
}
