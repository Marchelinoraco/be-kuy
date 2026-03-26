<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\UserDevice;
use Carbon\Carbon;
use App\Http\Controllers\Controller;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required','email'],
            'password' => ['required']
        ]);

        if (!Auth::attempt($credentials)) {

            return response()->json([
                'status' => false,
                'message' => 'Email atau password salah'
            ], 401);

        }

        $user = Auth::user();

        // Cek apakah akun aktif
        if (!$user->is_active) {
            Auth::logout();

            return response()->json([
                'status' => false,
                'message' => 'Akun dinonaktifkan'
            ], 403);
        }

        // Pastikan hanya admin yang bisa login dari endpoint ini
        if ($user->role !== 'admin') {
            Auth::logout();

            return response()->json([
                'status' => false,
                'message' => 'Akses ditolak. Bukan akun admin.'
            ], 403);
        }

        // Update last login
        $user->update([
            'last_login' => Carbon::now()
        ]);

        // Simpan / update device login
        UserDevice::updateOrCreate(
            [
                'user_id' => $user->id,
                'device' => $request->header('User-Agent')
            ],
            [
                'device_type' => 'web',
                'ip_address' => $request->ip(),
                'user_agent' => $request->header('User-Agent'),
                'last_login' => Carbon::now()
            ]
        );

        $token = $user
            ->createToken('admin_token')
            ->plainTextToken;

        return response()->json([
            'status' => true,
            'message' => 'Login admin berhasil',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'image' => $user->image,
                ],
                'token' => $token
            ]
        ]);
    }
    public function me(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'status' => true,
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'image' => $user->image,
                ]
            ]
        ]);
    }

    public function logout(Request $request)
    {
        $user = $request->user();

        if ($user && $request->user()->currentAccessToken()) {
            $request->user()->currentAccessToken()->delete();
        }

        return response()->json([
            'status' => true,
            'message' => 'Logout admin berhasil'
        ]);
    }
}
