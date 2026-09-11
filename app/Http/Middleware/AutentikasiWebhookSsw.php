<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Penjaga tambahan endpoint callback SSW.
 *
 * Autentikasinya sendiri ditangani `auth:sanctum` — pemanggil login lewat
 * POST /api/login dengan akun sistem, lalu memakai token itu sebagai Bearer.
 * Middleware ini menambahkan tiga pagar di atasnya: sakelar aktif/nonaktif,
 * daftar IP, dan pembatasan akun mana yang boleh mengirim keputusan.
 *
 * Jalankan SETELAH auth:sanctum supaya $request->user() sudah terisi.
 */
class AutentikasiWebhookSsw
{
    public function handle(Request $request, Closure $next): Response
    {
        $config = config('ssw.webhook');

        if (! ($config['enabled'] ?? false)) {
            return response()->json([
                'success' => false,
                'message' => 'Endpoint callback SSW sedang dinonaktifkan.',
            ], 503);
        }

        $ips = $config['allowed_ips'] ?? [];

        if ($ips !== [] && ! in_array($request->ip(), $ips, true)) {
            Log::warning('[SSW] Callback ditolak: IP di luar daftar.', ['ip' => $request->ip()]);

            return response()->json([
                'success' => false,
                'message' => 'Alamat IP tidak diizinkan.',
            ], 403);
        }

        $akun = $config['allowed_users'] ?? [];
        $email = $request->user()?->email;

        // Daftar kosong = semua akun sistem boleh. Isi daftarnya kalau ingin
        // hanya akun integrasi khusus yang bisa mengubah status berkas.
        if ($akun !== [] && ! in_array($email, $akun, true)) {
            Log::warning('[SSW] Callback ditolak: akun tidak diizinkan.', ['user' => $email]);

            return response()->json([
                'success' => false,
                'message' => 'Akun ini tidak diizinkan mengirim callback.',
            ], 403);
        }

        return $next($request);
    }
}
