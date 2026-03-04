<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Soal;
use Illuminate\Http\Request;

class SoalController extends Controller
{
    /* ===============================
       GET /api/soal
    ================================= */
  public function index(Request $request)
{
    $query = Soal::withCount('tryouts')->latest();

    // Filter status publish (aktif / nonaktif)
    if ($request->filled('status')) {
        $query->where('status', $request->status);
    }

    // Filter kategori
    if ($request->filled('category')) {
        $query->where('category', $request->category);
    }

    // Filter soal yang sudah dipakai
    if ($request->filled('used')) {
        if ($request->used === 'true') {
            $query->has('tryouts');
        } elseif ($request->used === 'false') {
            $query->doesntHave('tryouts');
        }
    }

    $soals = $query->get();

    return response()->json([
        'status' => true,
        'data'   => $soals
    ]);
}

    /* ===============================
       POST /api/soal
    ================================= */
    public function store(Request $request)
    {
        $data = $this->validateData($request);

        // ✅ Default status jika tidak dikirim
        $data['status'] = $data['status'] ?? 'nonaktif';

        $soal = Soal::create($data);

        return response()->json([
            'status'  => true,
            'message' => 'Soal berhasil disimpan',
            'data'    => $soal
        ], 201);
    }

    /* ===============================
       PUT /api/soal/{id}
    ================================= */
    public function update(Request $request, $id)
    {
        $soal = Soal::findOrFail($id);

        $data = $this->validateData($request);

        $soal->update($data);

        return response()->json([
            'status'  => true,
            'message' => 'Soal berhasil diupdate',
            'data'    => $soal
        ]);
    }

    /* ===============================
       DELETE /api/soal/{id}
    ================================= */
    public function destroy($id)
    {
        $soal = Soal::findOrFail($id);
        $soal->delete();

        return response()->json([
            'status'  => true,
            'message' => 'Soal berhasil dihapus'
        ]);
    }

    /* ===============================
       VALIDATION LOGIC
    ================================= */
    private function validateData(Request $request)
    {
        $data = $request->validate([
            'category'        => 'required|in:TWK,TIU,TKP',
            'sub_category'    => 'nullable|string',
            'difficulty'      => 'nullable|string',
            'question'        => 'required|string',
            'options'         => 'required|array|min:4',
            'options.*.label' => 'required|string',
            'options.*.text'  => 'required|string',
            'options.*.score' => 'nullable|integer|min:1|max:5',
            'correct_answer'  => 'nullable|string|in:A,B,C,D,E',
            'explanation'     => 'nullable|string',

            // ✅ Sekarang optional
            'status'          => 'nullable|in:aktif,nonaktif',
        ]);

        /* =============================
           Aturan TKP
        ============================= */
        if ($data['category'] === 'TKP') {
            foreach ($data['options'] as $option) {
                if (!isset($option['score'])) {
                    abort(422, 'Setiap opsi TKP wajib memiliki skor');
                }
            }
            $data['correct_answer'] = null;
        }

        /* =============================
           Aturan TWK / TIU
        ============================= */
        if ($data['category'] !== 'TKP') {
            if (empty($data['correct_answer'])) {
                abort(422, 'Jawaban benar wajib dipilih untuk TWK/TIU');
            }
        }

        return $data;
    }


}