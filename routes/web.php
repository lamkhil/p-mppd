<?php

use App\Filament\Pages\UploadDokumenSIP;
use Illuminate\Support\Facades\Route;

Route::get('/sip/upload/{record}', UploadDokumenSIP::class)
    ->name('sip.upload');
