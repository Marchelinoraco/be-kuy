<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TryoutRegistration;
use App\Models\TryoutResult;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TryoutResultController extends Controller
{
    public function show(Request $request, $tryoutId)
    {
        $user = $request->user();

        $result = TryoutResult::with([
            'tryout.soals' => function ($query) {
                $this->applyTryoutQuestionOrdering($query);
            },
        ])
            ->forUserTryout($user->id, $tryoutId)
            ->completed()
            ->latestAttempt()
            ->first();

        if (!$result || !$result->tryout) {
            return response()->json([
                'status' => false,
                'message' => 'Hasil tryout tidak ditemukan',
            ], 404);
        }

        $registration = TryoutRegistration::where('user_id', $user->id)
            ->where('tryout_id', $tryoutId)
            ->first();

        $tryout = $result->tryout;
        $answers = $this->normalizeAnswers($result->answers ?? []);
        $categoryScores = [
            'TWK' => 0,
            'TIU' => 0,
            'TKP' => 0,
        ];

        $questions = $tryout->soals->values()->map(function ($soal, $index) use ($answers, &$categoryScores) {
            $userAnswer = $answers[$soal->id] ?? $answers[(string) $soal->id] ?? null;
            $options = $this->normalizeOptions($soal->options ?? []);
            $category = $this->normalizeCategory($soal->category);

            $status = 'unanswered';
            $questionScore = 0;
            $correctAnswer = strtoupper((string) ($soal->correct_answer ?? ''));

            if ($category === 'TKP') {
                $selectedOption = collect($options)->firstWhere('key', $userAnswer);
                $bestOption = collect($options)
                    ->sortByDesc(fn($option) => (int) ($option['score'] ?? 0))
                    ->first();

                $bestScore = (int) ($bestOption['score'] ?? 0);
                $questionScore = (int) ($selectedOption['score'] ?? 0);
                $correctAnswer = $bestOption['key'] ?? null;

                if ($userAnswer) {
                    $status = $questionScore === $bestScore ? 'correct' : 'wrong';
                }

                $categoryScores['TKP'] += $questionScore;
            } else {
                if ($userAnswer) {
                    $status = $userAnswer === $correctAnswer ? 'correct' : 'wrong';
                }

                if ($status === 'correct') {
                    $questionScore = 5;
                    $categoryScores[$category] += 5;
                }
            }

            return [
                'id' => $soal->id,
                'number' => $index + 1,
                'category' => $category,
                'question' => $soal->question,
                'options' => $options,
                'correct_answer' => $correctAnswer ?: null,
                'user_answer' => $userAnswer,
                'explanation' => $soal->explanation,
                'score' => $questionScore,
                'status' => $status,
            ];
        })->all();

        $score = (int) ($result->score ?? 0);
        $rank = $this->resolveRank($tryoutId, $score, $user->id);

        $finishedAt = $registration?->finished_at;
        $computedTotalScore = $categoryScores['TWK'] + $categoryScores['TIU'] + $categoryScores['TKP'];
        $passed = $categoryScores['TWK'] >= (int) ($tryout->twk_pg ?? 0)
            && $categoryScores['TIU'] >= (int) ($tryout->tiu_pg ?? 0)
            && $categoryScores['TKP'] >= (int) ($tryout->tkp_pg ?? 0);

        $attemptHistory = TryoutResult::forUserTryout($user->id, $tryoutId)
            ->completed()
            ->latestAttempt()
            ->get()
            ->map(function (TryoutResult $attempt) {
                return [
                    'id' => $attempt->id,
                    'attempt_number' => (int) ($attempt->attempt_number ?? 1),
                    'score' => (int) ($attempt->score ?? 0),
                    'correct_answer' => (int) ($attempt->correct_answer ?? 0),
                    'started_at' => optional($attempt->started_at)->toDateTimeString(),
                    'finished_at' => optional($attempt->finished_at)->toDateTimeString(),
                ];
            })
            ->values()
            ->all();

        return response()->json([
            'status' => true,
            'data' => [
                'attempt_number' => (int) ($result->attempt_number ?? 1),
                'score' => $score,
                'rank' => $rank,
                'correct_answer' => (int) ($result->correct_answer ?? 0),
                'answers' => $answers,
                'session_state' => $this->sessionStateForResponse($result),
                'started_at' => optional($result->started_at)->toDateTimeString(),
                'finished_at' => optional($result->finished_at ?? $finishedAt)->toDateTimeString(),
                'tryout' => [
                    'id' => $tryout->id,
                    'title' => $tryout->title,
                    'duration' => (int) ($tryout->duration ?? 0),
                    'question_count' => count($questions),
                    'twk_pg' => (int) ($tryout->twk_pg ?? 0),
                    'tiu_pg' => (int) ($tryout->tiu_pg ?? 0),
                    'tkp_pg' => (int) ($tryout->tkp_pg ?? 0),
                ],
                'summary' => [
                    'tryout_name' => $tryout->title,
                    'date' => optional($finishedAt ?? $result->started_at)->toDateTimeString(),
                    'duration' => (int) ($tryout->duration ?? 0),
                    'question_count' => count($questions),
                    'twk' => $categoryScores['TWK'],
                    'tiu' => $categoryScores['TIU'],
                    'tkp' => $categoryScores['TKP'],
                    'total_score' => $computedTotalScore,
                    'passed' => $passed,
                ],
                'attempt_history' => $attemptHistory,
                'questions' => $questions,
            ],
        ]);
    }

    public function history(Request $request)
    {
        $user = $request->user();

        $results = TryoutResult::where('user_id', $user->id)
            ->latestAttempt()
            ->get();

        return response()->json([
            'status' => true,
            'data' => $results,
        ]);
    }

    private function normalizeAnswers(array $answers): array
    {
        return collect($answers)
            ->mapWithKeys(function ($answer, $questionId) {
                if ($answer === null || $answer === '') {
                    return [$questionId => null];
                }

                return [$questionId => strtoupper((string) $answer)];
            })
            ->all();
    }

    private function normalizeOptions(array $options): array
    {
        return collect($options)
            ->map(function ($option) {
                $key = strtoupper((string) data_get($option, 'label', ''));

                if ($key === '') {
                    return null;
                }

                return [
                    'key' => $key,
                    'text' => (string) data_get($option, 'text', ''),
                    'score' => data_get($option, 'score') !== null ? (int) data_get($option, 'score') : null,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    private function normalizeCategory(?string $category): string
    {
        $normalized = strtoupper(trim((string) $category));

        return in_array($normalized, ['TWK', 'TIU', 'TKP'], true)
            ? $normalized
            : ($normalized !== '' ? $normalized : '-');
    }

    private function sessionStateForResponse(?TryoutResult $result): array
    {
        if (!Schema::hasColumn('tryout_results', 'session_state')) {
            return [
                'current_index' => 0,
                'current_question_id' => null,
                'flagged_question_ids' => [],
                'visited_question_ids' => [],
                'last_interaction' => null,
            ];
        }

        return $result?->session_state ?? [
            'current_index' => 0,
            'current_question_id' => null,
            'flagged_question_ids' => [],
            'visited_question_ids' => [],
            'last_interaction' => null,
        ];
    }

    private function latestCompletedResultsQuery(int $tryoutId)
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

    private function resolveRank(int $tryoutId, int $score, int $userId): int
    {
        return (clone $this->latestCompletedResultsQuery($tryoutId))
            ->where(function ($query) use ($score, $userId) {
                $query
                    ->where('tryout_results.score', '>', $score)
                    ->orWhere(function ($innerQuery) use ($score, $userId) {
                        $innerQuery
                            ->where('tryout_results.score', $score)
                            ->where('tryout_results.user_id', '<', $userId);
                    });
            })
            ->count() + 1;
    }

    private function applyTryoutQuestionOrdering($query): void
    {
        $query
            ->orderByRaw("
                CASE soals.category
                    WHEN 'TWK' THEN 1
                    WHEN 'TIU' THEN 2
                    WHEN 'TKP' THEN 3
                    ELSE 4
                END
            ")
            ->orderBy('pivot_urutan_soal')
            ->orderBy('soals.id');
    }
}
