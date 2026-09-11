<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Autentikasi API untuk sistem luar (SSW).
 *
 * Bentuknya sengaja dibuat mirip dengan /api/login milik SSW: kirim
 * kredensial, terima token, pakai token itu sebagai Bearer. Bedanya
 * kredensial di sini adalah akun sistem P-MPPD, bukan akun SSW.
 */
class AuthController extends Controller
{
    /** POST /api/login */
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            // `username` diterima sebagai alias `email` supaya pemanggil yang
            // terbiasa dengan bentuk request SSW tidak perlu menyesuaikan.
            'email' => ['required_without:username', 'nullable', 'string'],
            'username' => ['required_without:email', 'nullable', 'string'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:255'],
        ]);

        $email = $data['email'] ?? $data['username'];

        $user = \App\Models\User::where('email', $email)->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            Log::warning('[API] Login gagal', ['email' => $email, 'ip' => $request->ip()]);

            throw ValidationException::withMessages([
                'email' => ['Kredensial tidak cocok.'],
            ]);
        }

        // Satu token per perangkat/konsumen supaya bisa dicabut sendiri-sendiri.
        $namaToken = $data['device_name'] ?? 'api-'.$request->ip();

        $token = $user->createToken($namaToken);

        Log::info('[API] Login berhasil', ['user' => $user->email, 'token' => $namaToken, 'ip' => $request->ip()]);

        return response()->json([
            'success' => true,
            'token' => $token->plainTextToken,
            'token_name' => $namaToken,
            'expired' => optional($token->accessToken->expires_at)->toDateTimeString(),
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
        ]);
    }

    /** POST /api/logout — mencabut token yang sedang dipakai. */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Token dicabut.',
        ]);
    }

    /** GET /api/me — untuk memastikan token masih hidup. */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
        ]);
    }
}
