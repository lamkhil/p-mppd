<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Sakelar Integrasi
    |--------------------------------------------------------------------------
    |
    | Saat `false`, semua pemanggilan lewat SswMppdService akan ditolak lebih
    | awal (SswDisabledException) tanpa menyentuh jaringan. Berguna di lokal
    | dan saat staging supaya tidak mengirim data ke SSW produksi.
    |
    */
    'enabled' => (bool) env('SSW_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Endpoint & Kredensial
    |--------------------------------------------------------------------------
    |
    | `base_url` adalah prefix untuk seluruh endpoint (tanpa trailing slash).
    | Endpoint yang dipakai: /login, /integrasi/ssw-master-izin, /integrasi/ssw-mppd
    |
    */
    'base_url' => rtrim(env('SSW_BASE_URL', 'https://kantorku.surabaya.go.id/api'), '/'),
    'username' => env('SSW_USERNAME'),
    'password' => env('SSW_PASSWORD'),

    'endpoints' => [
        'login' => env('SSW_ENDPOINT_LOGIN', 'login'),
        'master_izin' => env('SSW_ENDPOINT_MASTER_IZIN', 'integrasi/ssw-master-izin'),
        'mppd' => env('SSW_ENDPOINT_MPPD', 'integrasi/ssw-mppd'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Layanan MPPD
    |--------------------------------------------------------------------------
    |
    | id_layanan 36 = "Layanan MPPD" sesuai dokumentasi SSW.
    |
    */
    'id_layanan' => (int) env('SSW_ID_LAYANAN', 36),

    /*
    |--------------------------------------------------------------------------
    | Perilaku HTTP
    |--------------------------------------------------------------------------
    |
    | `request_format` menentukan cara body POST /integrasi/ssw-mppd dikirim:
    |   multipart → multipart/form-data (sesuai contoh di dokumentasi)
    |   form      → application/x-www-form-urlencoded
    |   json      → application/json (RAW)
    |
    | `retry` hanya berlaku untuk kegagalan koneksi (timeout / DNS / TLS),
    | bukan untuk response 4xx — response error ditangani eksplisit.
    |
    */
    'request_format' => env('SSW_REQUEST_FORMAT', 'multipart'),
    'timeout' => (int) env('SSW_TIMEOUT', 30),
    'connect_timeout' => (int) env('SSW_CONNECT_TIMEOUT', 10),
    'verify_ssl' => (bool) env('SSW_VERIFY_SSL', true),

    'retry' => [
        'times' => (int) env('SSW_RETRY_TIMES', 2),
        'sleep' => (int) env('SSW_RETRY_SLEEP', 500), // milidetik
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Token & Master Izin
    |--------------------------------------------------------------------------
    |
    | Token Sanctum dari /api/login disimpan di cache lalu dipakai ulang.
    | Dokumentasi SSW tidak menyebut masa berlaku token, jadi TTL dibuat
    | konservatif dan client otomatis login ulang sekali saat menerima 401.
    |
    */
    'cache' => [
        'store' => env('SSW_CACHE_STORE'), // null = store default aplikasi
        'token_key' => env('SSW_TOKEN_CACHE_KEY', 'ssw:token'),
        'token_ttl' => (int) env('SSW_TOKEN_TTL', 1800),      // 30 menit
        'master_izin_key' => env('SSW_MASTER_IZIN_CACHE_KEY', 'ssw:master-izin'),
        'master_izin_ttl' => (int) env('SSW_MASTER_IZIN_TTL', 86400), // 24 jam
    ],

    /*
    |--------------------------------------------------------------------------
    | Nilai Default Payload MPPD
    |--------------------------------------------------------------------------
    |
    | Dipakai saat record SIP belum punya nilainya sendiri.
    | `link_syarat` null → otomatis memakai route('sip.upload', $record).
    |
    */
    'defaults' => [
        'id_ijin' => env('SSW_DEFAULT_ID_IJIN') !== null ? (int) env('SSW_DEFAULT_ID_IJIN') : null,
        'jenis_permohonan' => env('SSW_DEFAULT_JENIS_PERMOHONAN', 'Baru'),
        'link_syarat' => env('SSW_DEFAULT_LINK_SYARAT'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Peta Status Alur SSW
    |--------------------------------------------------------------------------
    |
    | Alurnya: berkas yang berkasnya sudah lengkap (`boleh_kirim`) dikirim ke
    | SSW, lalu MENGENDAP di `setelah_kirim` tanpa aksi apa pun sampai dinas
    | teknis memutuskan. Keputusan itu masuk lewat webhook dan memindahkan
    | berkas ke `disetujui` atau `ditolak`.
    |
    | Ubah di sini kalau alur di lapangan berbeda — tidak perlu menyentuh kode.
    |
    */
    'status' => [
        'boleh_kirim' => ['proses'],
        'setelah_kirim' => 'menunggu_verifikasi_teknis',
        'disetujui' => 'terverifikasi_teknis',
        'ditolak' => 'ditolak_teknis',
    ],

    /*
    |--------------------------------------------------------------------------
    | Webhook Balikan dari SSW
    |--------------------------------------------------------------------------
    |
    | Endpoint: POST /api/ssw/callback
    |
    | Dokumentasi SSW tidak memuat spesifikasi callback sama sekali, jadi
    | kontraknya kita yang tentukan dan serahkan ke tim SSW. Autentikasinya
    | memakai login sistem: SSW memanggil POST /api/login dengan akun yang
    | kita buatkan, lalu memakai token hasilnya sebagai Bearer (Sanctum).
    |
    | `peta_status` menerjemahkan kosakata SSW ke status internal. Tambahkan
    | varian kalau SSW ternyata memakai istilah lain — pembandingan dilakukan
    | dalam huruf kecil.
    |
    */
    'webhook' => [
        'enabled' => (bool) env('SSW_WEBHOOK_ENABLED', true),

        // Kosong = terima dari IP mana pun (token login tetap wajib).
        'allowed_ips' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('SSW_WEBHOOK_IPS', ''))
        ))),

        // Email akun sistem yang boleh mengirim callback. Kosong = semua akun
        // yang bisa login. Isi dengan akun integrasi khusus untuk produksi.
        'allowed_users' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('SSW_WEBHOOK_USERS', ''))
        ))),

        'peta_status' => [
            'disetujui' => 'disetujui',
            'setuju' => 'disetujui',
            'diterima' => 'disetujui',
            'approved' => 'disetujui',
            'verified' => 'disetujui',
            'ditolak' => 'ditolak',
            'tolak' => 'ditolak',
            'rejected' => 'ditolak',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Pilihan Jenis Permohonan
    |--------------------------------------------------------------------------
    |
    | Dokumentasi SSW tidak mencantumkan daftar nilai yang sah untuk
    | `jenis_permohonan`. Daftar di bawah dipakai sebagai opsi di form; ubah
    | di sini kalau SSW memberi daftar resminya.
    |
    */
    'jenis_permohonan_options' => [
        'Baru' => 'Baru',
        'Perpanjangan' => 'Perpanjangan',
        'Perubahan' => 'Perubahan',
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    |
    | Channel log untuk jejak request/response SSW. null = channel default.
    | Password dan token selalu di-mask sebelum ditulis.
    |
    */
    'log_channel' => env('SSW_LOG_CHANNEL'),
];
