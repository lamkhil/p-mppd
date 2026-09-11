<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SswCallbackRequest;
use App\Services\Ssw\Exceptions\SswCallbackException;
use App\Services\Ssw\SswCallbackService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

/**
 * POST /api/ssw/callback
 *
 * Menerima keputusan verifikasi teknis dari SSW dan memindahkan berkas dari
 * status menunggu ke terverifikasi/ditolak teknis.
 */
class SswCallbackController extends Controller
{
    public function __invoke(SswCallbackRequest $request, SswCallbackService $callback): JsonResponse
    {
        $payload = $request->validated();

        Log::info('[SSW] Callback diterima', [
            'nomor_register' => $payload['nomor_register'],
            'status' => $payload['status'],
            'ip' => $request->ip(),
        ]);

        try {
            $hasil = $callback->proses($payload);
        } catch (SswCallbackException $e) {
            Log::warning('[SSW] Callback ditolak: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $e->httpStatus);
        }

        return response()->json([
            'success' => true,
            'message' => $hasil['duplikat']
                ? 'Callback sudah pernah diproses, tidak ada perubahan.'
                : 'Status permohonan berhasil diperbarui.',
            'data' => [
                'nomor_register' => $hasil['record']->nomor_register,
                'status' => $hasil['status'],
                'duplikat' => $hasil['duplikat'],
                'diproses_pada' => now()->toIso8601String(),
            ],
        ]);
    }
}
