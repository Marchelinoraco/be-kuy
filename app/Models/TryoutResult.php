<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class TryoutResult extends Model
{
    private static ?bool $hasAttemptNumberColumn = null;
    private static ?bool $hasStatusColumn = null;
    private static ?bool $hasFinishedAtColumn = null;

    protected $fillable = [
        'user_id',
        'tryout_id',
        'attempt_number',
        'status',
        'score',
        'correct_answer',
        'answers',
        'session_state',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'attempt_number' => 'integer',
        'score' => 'integer',
        'correct_answer' => 'integer',
        'answers' => 'array',
        'session_state' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function tryout()
    {
        return $this->belongsTo(Tryout::class);
    }

    public function scopeForUserTryout($query, int $userId, int $tryoutId)
    {
        return $query
            ->where('user_id', $userId)
            ->where('tryout_id', $tryoutId);
    }

    public function scopeCompleted($query)
    {
        if (!self::hasStatusColumn()) {
            return $query;
        }

        return $query->where('status', 'completed');
    }

    public function scopeInProgress($query)
    {
        if (!self::hasStatusColumn()) {
            return $query;
        }

        return $query->where('status', 'in_progress');
    }

    public function scopeLatestAttempt($query)
    {
        if (!self::hasAttemptNumberColumn()) {
            return $query->orderByDesc('id');
        }

        return $query->orderByDesc('attempt_number')->orderByDesc('id');
    }

    public static function hasAttemptNumberColumn(): bool
    {
        if (self::$hasAttemptNumberColumn === null) {
            self::$hasAttemptNumberColumn = Schema::hasColumn('tryout_results', 'attempt_number');
        }

        return self::$hasAttemptNumberColumn;
    }

    public static function hasStatusColumn(): bool
    {
        if (self::$hasStatusColumn === null) {
            self::$hasStatusColumn = Schema::hasColumn('tryout_results', 'status');
        }

        return self::$hasStatusColumn;
    }

    public static function hasFinishedAtColumn(): bool
    {
        if (self::$hasFinishedAtColumn === null) {
            self::$hasFinishedAtColumn = Schema::hasColumn('tryout_results', 'finished_at');
        }

        return self::$hasFinishedAtColumn;
    }
}
