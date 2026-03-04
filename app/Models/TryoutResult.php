<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TryoutResult extends Model
{
    protected $fillable = [
        'user_id',
        'tryout_id',
        'score',
        'answers',
        'started_at'
    ];

    protected $casts = [
        'answers' => 'array',
        'started_at' => 'datetime'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function tryout()
    {
        return $this->belongsTo(Tryout::class);
    }
}