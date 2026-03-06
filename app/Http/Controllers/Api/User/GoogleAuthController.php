<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class GoogleAuthController extends Controller
{
    /**
     * Redirect ke Google
     */
    public function redirect()
    {
        return Socialite::driver('google')
            ->stateless()
            ->redirect();
    }

    /**
     * Callback dari Google
     */
    public function callback(Request $request)
    {
        $googleUser = Socialite::driver('google')
            ->stateless()
            ->user();

        $user = User::updateOrCreate(
            [
                'email' => $googleUser->getEmail()
            ],
            [
                'name' => $googleUser->getName(),
                'provider' => 'google',
                'provider_id' => $googleUser->getId(),
                'password' => bcrypt(Str::random(16)),
                'role' => 'user',
                'is_active' => true
            ]
        );

        // Update last login
        $user->update([
            'last_login' => Carbon::now(),
            'device_login' => $request->header('User-Agent')
        ]);

        // Simpan device login ke tabel user_devices
        DB::table('user_devices')->insert([
            'user_id' => $user->id,
            'device' => $request->header('User-Agent'),
            'ip_address' => $request->ip(),
            'last_login' => Carbon::now(),
            'created_at' => Carbon::now()
        ]);

        $token = $user->createToken('google_token')->plainTextToken;

        return response()->json([
            'status' => true,
            'message' => 'Login Google berhasil',
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
}