<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Threshold Eskalasi "Hari Berjalan"
    |--------------------------------------------------------------------------
    |
    | Menentukan warna badge kolom "Hari Berjalan" pada tabel SIP.
    | Pemetaan: jika jumlah hari berjalan <= nilai threshold, gunakan warna
    | terkait. Selebihnya jatuh ke `danger`.
    |
    */
    'hari_berjalan' => [
        'success' => env('SIP_HARI_SUCCESS', 1),
        'info'    => env('SIP_HARI_INFO', 2),
        'warning' => env('SIP_HARI_WARNING', 3),
        // > warning  → danger
    ],

    /*
    |--------------------------------------------------------------------------
    | Pengingat SIP Kedaluwarsa
    |--------------------------------------------------------------------------
    |
    | Jendela hari ke depan untuk menampilkan SIP yang akan kedaluwarsa di
    | widget dashboard / laporan.
    |
    */
    'kedaluwarsa_window_days' => env('SIP_KEDALUWARSA_WINDOW', 60),

    /*
    |--------------------------------------------------------------------------
    | Filter "Akan Kedaluwarsa" di tabel utama
    |--------------------------------------------------------------------------
    */
    'filter_akan_expired_days' => env('SIP_FILTER_EXPIRED_DAYS', 30),
];
