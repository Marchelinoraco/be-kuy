<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TryoutResult;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class RankingController extends Controller
{
    public function index($tryoutId)
    {
        $ranking = Cache::remember("ranking_tryout_{$tryoutId}", 10, function () use ($tryoutId) {
            return $this->latestCompletedResultsQuery($tryoutId)
                ->with('user:id,name')
                ->select('tryout_results.*')
                ->orderByDesc('score')
                ->orderBy('user_id')
                ->limit(100)
                ->get();
        });

        return response()->json([
            'status' => true,
            'data' => $ranking
        ]);
    }

    public function myRank(Request $request, $tryoutId)
    {
        $userId = $request->user()->id;

        $userResult = $this->latestCompletedResultsQuery($tryoutId)
            ->select('tryout_results.user_id', 'tryout_results.score')
            ->where('tryout_results.user_id', $userId)
            ->first();

        if (!$userResult) {
            return response()->json([
                'status' => false,
                'message' => 'User belum mengerjakan tryout'
            ]);
        }

        $rank = $this->latestCompletedResultsQuery($tryoutId)
            ->where(function ($query) use ($userResult) {
                $query
                    ->where('tryout_results.score', '>', $userResult->score)
                    ->orWhere(function ($innerQuery) use ($userResult) {
                        $innerQuery
                            ->where('tryout_results.score', $userResult->score)
                            ->where('tryout_results.user_id', '<', $userResult->user_id);
                    });
            })
            ->count() + 1;

        return response()->json([
            'status' => true,
            'rank' => $rank
        ]);
    }

    private function latestCompletedResultsQuery($tryoutId)
    {
        if (!TryoutResult::hasAttemptNumberColumn() || !TryoutResult::hasStatusColumn()) {
            return TryoutResult::query()->where('tryout_id', $tryoutId);
        }

        $latestCompletedAttempts = TryoutResult::query()
            ->select('user_id', DB::raw('MAX(attempt_number) as latest_attempt_number'))
            ->where('tryout_id', $tryoutId)
            ->where('status', 'completed')
            ->groupBy('user_id');

        return TryoutResult::query()
            ->joinSub($latestCompletedAttempts, 'latest_completed_attempts', function ($join) {
                $join->on('tryout_results.user_id', '=', 'latest_completed_attempts.user_id')
                    ->on('tryout_results.attempt_number', '=', 'latest_completed_attempts.latest_attempt_number');
            })
            ->where('tryout_results.tryout_id', $tryoutId)
            ->where('tryout_results.status', 'completed');
    }
}
