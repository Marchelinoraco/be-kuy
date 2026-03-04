<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tryout;
use App\Models\Soal;
use App\Models\TryoutResult;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class TryoutController extends Controller
{
    public function index()
    {
        return response()->json([
            'status' => true,
            'data' => Tryout::withCount('soals')->latest()->get()
        ]);
    }

    public function show($id)
    {
        return response()->json([
            'status' => true,
            'data' => Tryout::with('soals')->findOrFail($id)
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required',
            'duration' => 'required|integer',
            'twk_count' => 'required|integer',
            'tiu_count' => 'required|integer',
            'tkp_count' => 'required|integer',
            'twk_pg' => 'required|integer',
            'tiu_pg' => 'required|integer',
            'tkp_pg' => 'required|integer',
        ]);

        $tryout = Tryout::create([
            'title' => $request->title,
            'duration' => $request->duration,
            'status' => 'draft',
            'twk_target' => $request->twk_count,
            'tiu_target' => $request->tiu_count,
            'tkp_target' => $request->tkp_count,
            'twk_pg' => $request->twk_pg,
            'tiu_pg' => $request->tiu_pg,
            'tkp_pg' => $request->tkp_pg,
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Tryout berhasil dibuat',
            'data' => $tryout
        ], 201);
    }

    /* ================= ATTACH ================= */

    public function attachSoal(Request $request, $id)
    {
        $tryout = Tryout::with('soals')->findOrFail($id);

        if ($tryout->status === 'publish') {
            return response()->json([
                'status' => false,
                'message' => 'Tryout sudah dipublish'
            ], 422);
        }

        $request->validate([
            'soal_id' => 'required|exists:soals,id'
        ]);

        $soal = Soal::findOrFail($request->soal_id);

        if ($tryout->soals->contains($soal->id)) {
            return response()->json([
                'status' => false,
                'message' => 'Soal sudah ada'
            ], 422);
        }

        $currentCount = $tryout->soals
            ->where('category', $soal->category)
            ->count();

        $target = match ($soal->category) {
            'TWK' => $tryout->twk_target,
            'TIU' => $tryout->tiu_target,
            'TKP' => $tryout->tkp_target,
            default => 0
        };

        if ($currentCount >= $target) {
            return response()->json([
                'status' => false,
                'message' => 'Kuota kategori penuh'
            ], 422);
        }

        $tryout->soals()->attach($soal->id);

        return response()->json([
            'status' => true,
            'message' => 'Soal berhasil ditambahkan'
        ]);
    }

    /* ================= DETACH ================= */

    public function detachSoal($id, $soalId)
    {
        $tryout = Tryout::findOrFail($id);

        if ($tryout->status === 'publish') {
            return response()->json([
                'status' => false,
                'message' => 'Tryout sudah dipublish'
            ], 422);
        }

        $tryout->soals()->detach($soalId);

        return response()->json([
            'status' => true,
            'message' => 'Soal berhasil dihapus'
        ]);
    }

    /* ================= PUBLISH ================= */

    public function publish($id)
    {
        $tryout = Tryout::with('soals')->findOrFail($id);

        $twk = $tryout->soals->where('category', 'TWK')->count();
        $tiu = $tryout->soals->where('category', 'TIU')->count();
        $tkp = $tryout->soals->where('category', 'TKP')->count();

        if (
            $twk !== $tryout->twk_target ||
            $tiu !== $tryout->tiu_target ||
            $tkp !== $tryout->tkp_target
        ) {
            return response()->json([
                'status' => false,
                'message' => 'Komposisi belum sesuai'
            ], 422);
        }

        $tryout->update(['status' => 'publish']);

        return response()->json([
            'status' => true,
            'message' => 'Tryout berhasil dipublish'
        ]);
    }
    /* ================= START TRYOUT ================= */

    public function start(Request $request, $id)
    {
        $user = $request->user();

        $tryout = Tryout::findOrFail($id);

        $session = TryoutResult::firstOrCreate(
            [
                'user_id' => $user->id,
                'tryout_id' => $tryout->id
            ],
            [
                'answers' => [],
                'score' => 0,
                'started_at' => Carbon::now()
            ]
        );

        $endTime = Carbon::parse($session->started_at)
            ->addMinutes($tryout->duration);

        return response()->json([
            'status' => true,
            'data' => [
                'started_at' => $session->started_at,
                'end_time' => $endTime,
                'duration' => $tryout->duration
            ]
        ]);
    }

    /* ================= AUTO SAVE ================= */

    public function autosave(Request $request, $id)
    {
        $user = $request->user();

        $request->validate([
            'answers' => 'required|array'
        ]);

        TryoutResult::where('user_id', $user->id)
            ->where('tryout_id', $id)
            ->update([
                'answers' => $request->answers
            ]);

        return response()->json([
            'status' => true,
            'message' => 'Jawaban tersimpan'
        ]);
    }

    /* ================= REMAINING TIME ================= */

    public function remainingTime(Request $request, $id)
    {
        $user = $request->user();

        $result = TryoutResult::where('user_id', $user->id)
            ->where('tryout_id', $id)
            ->firstOrFail();

        $tryout = Tryout::findOrFail($id);

        $end = Carbon::parse($result->started_at)
            ->addMinutes($tryout->duration);

        $remaining = $end->diffInSeconds(Carbon::now(), false);

        return response()->json([
            'status' => true,
            'remaining_seconds' => max($remaining, 0)
        ]);
    }

    /* ================= SUBMIT TRYOUT ================= */

    public function submit(Request $request, $id)
    {
        $user = $request->user();

        $tryout = Tryout::with('soals')->findOrFail($id);

        $request->validate([
            'answers' => 'required|array'
        ]);

        $answers = $request->answers;

        $score = 0;
        $detail = [];

        $categoryScore = [
            'TWK' => 0,
            'TIU' => 0,
            'TKP' => 0
        ];

        foreach ($tryout->soals as $soal) {
            $userAnswer = $answers[$soal->id] ?? null;
            $isCorrect = false;

            if ($soal->category !== 'TKP') {

                if ($userAnswer && $userAnswer === $soal->correct_answer) {
                    $score++;
                    $categoryScore[$soal->category]++;
                    $isCorrect = true;
                }

            } else {

                // TKP scoring based on option score
                if ($userAnswer) {

                    $selected = collect($soal->options)->firstWhere('label', $userAnswer);

                    if ($selected && isset($selected['score'])) {

                        $nilai = (int) $selected['score'];

                        $score += $nilai;
                        $categoryScore['TKP'] += $nilai;

                        $isCorrect = true;
                    }
                }
            }

            $detail[] = [
                'soal_id' => $soal->id,
                'category' => $soal->category,
                'question' => $soal->question,
                'options' => $soal->options,
                'user_answer' => $userAnswer,
                'correct_answer' => $soal->correct_answer,
                'explanation' => $soal->explanation,
                'is_correct' => $isCorrect
            ];
        }

        // Simpan atau update hasil tryout user
        TryoutResult::updateOrCreate(
            [
                'user_id' => $user->id,
                'tryout_id' => $tryout->id
            ],
            [
                'score' => $score,
                'answers' => $answers
            ]
        );
        Cache::forget("ranking_tryout_{$id}");

        return response()->json([
            'status' => true,
            'message' => 'Tryout selesai',
            'data' => [
                'score' => $score
            ]
        ]);
    }
}