<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserDevice extends Model
{
    protected $fillable = [
        'user_id',
        'device',
        'device_type',
        'ip_address',
        'user_agent',
        'last_login'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}