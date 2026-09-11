<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\SswCallbackController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Integrasi
|--------------------------------------------------------------------------
|
| Alur pemakaian oleh SSW:
|   1. POST /api/login  → dapat Bearer token (akun sistem P-MPPD)
|   2. POST /api/ssw/callback dengan header Authorization: Bearer <token>
|
| Semuanya stateless: tanpa sesi, tanpa CSRF.
|
*/

Route::post('/login', [AuthController::class, 'login'])
    ->middleware('throttle:10,1')
    ->name('api.login');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('api.logout');
    Route::get('/me', [AuthController::class, 'me'])->name('api.me');

    Route::post('/ssw/callback', SswCallbackController::class)
        ->middleware(['ssw.webhook', 'throttle:60,1'])
        ->name('api.ssw.callback');
});
