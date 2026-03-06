<?php

namespace App\Http\Controllers\Api\User;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\UserDevice;
use Carbon\Carbon;
use App\Http\Controllers\Controller;

class UserAuthController extends Controller
{
    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => ['required','string','max:255'],
            'email' => ['required','email','unique:users,email'],
            'password' => ['required','min:6']
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'role' => 'user',
            'is_active' => true
        ]);

        $token = $user->createToken('user_token')->plainTextToken;

        return response()->json([
            'status' => true,
            'message' => 'Register berhasil',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role
                ],
                'token' => $token
            ]
        ], 201);
    }

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

        // Pastikan hanya user biasa
        if ($user->role !== 'user') {

            Auth::logout();

            return response()->json([
                'status' => false,
                'message' => 'Akses ditolak. Bukan akun user.'
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
            ->createToken('user_token')
            ->plainTextToken;

        return response()->json([
            'status' => true,
            'message' => 'Login user berhasil',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role
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
                    'role' => $user->role
                ]
            ]
        ]);
    }

    public function logout(Request $request)
    {
        $user = $request->user();

        if ($user) {
            // Logout dari semua device
            $user->tokens()->delete();
        }

        return response()->json([
            'status' => true,
            'message' => 'Logout berhasil'
        ]);
    }
}