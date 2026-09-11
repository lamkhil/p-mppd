<?php

use App\Models\SuratIzinPraktik;
use App\Services\Ssw\Exceptions\SswAuthenticationException;
use App\Services\Ssw\Exceptions\SswDisabledException;
use App\Services\Ssw\Exceptions\SswRequestException;
use App\Services\Ssw\Exceptions\SswValidationException;
use App\Services\Ssw\SswClient;
use App\Services\Ssw\SswMppdPayload;
use App\Services\Ssw\SswMppdService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config()->set('ssw', array_merge(config('ssw'), [
        'enabled' => true,
        'base_url' => 'https://ssw.test/api',
        'username' => 'petugas',
        'password' => 'rahasia',
        'retry' => ['times' => 1, 'sleep' => 0],
    ]));

    Cache::flush();
});

function sswService(): SswMppdService
{
    return new SswMppdService(new SswClient(config('ssw')), config('ssw'));
}

/**
 * Record in-memory: pemetaan payload & pemanggilan HTTP tidak butuh DB,
 * jadi tes ini bisa jalan tanpa driver database apa pun.
 */
function sswRecord(array $atribut = []): SuratIzinPraktik
{
    return new SuratIzinPraktik(array_merge([
        'nik' => '3578.0122.0325.0001',
        'nama' => 'Budi Santoso',
        'alamat' => 'Jl. Kenjeran No. 1',
        'email' => 'budi@example.test',
        'nomor_telepon' => '0812-3456-7890',
        'nomor_str' => 'STR-35780122032025-001',
        'masa_berlaku_str' => '5 Tahun',
        'nomor_register' => 'REG-2025-0012345',
        'profesi' => 'Dokter Umum',
        'tempat_praktik' => 'Klinik Sehat Sentosa',
        'alamat_tempat_praktik' => 'Jl. Raya Darmo No. 123',
        'nomor_sip' => 'SIP-503.445/2025/DINKES',
        'tanggal_terbit_sip' => '2025-09-09',
        'tanggal_akhir_sip' => '2030-09-08',
        'keterangan' => 'Sudah diverifikasi.',
        'status' => 'proses',
    ], $atribut));
}

/** Balasan login SSW yang valid. */
function sswResponseLogin(string $token = 'TOKEN-1'): array
{
    return ['token' => $token, 'user' => ['username' => 'petugas', 'kode_instansi' => '5.02.00.00.00']];
}

/* =========================================================================
 * Autentikasi
 * ========================================================================= */

it('login sekali lalu memakai ulang token dari cache', function () {
    Http::fake([
        'ssw.test/api/login' => Http::response(sswResponseLogin()),
        'ssw.test/api/integrasi/ssw-master-izin*' => Http::response([
            'success' => true,
            'data' => [['id_layanan' => 36, 'id_ijin' => 9000, 'nama_perizinan' => 'MPPD Nakes']],
            'message' => 'Data Ditemukan',
        ]),
    ]);

    $client = new SswClient(config('ssw'));
    $client->masterIzin();
    $client->masterIzin();

    Http::assertSentCount(3); // 1x login + 2x master izin
    expect(Cache::get('ssw:token'))->toBe('TOKEN-1');
});

it('mengirim bearer token pada endpoint terproteksi', function () {
    Http::fake([
        'ssw.test/api/login' => Http::response(sswResponseLogin('ABC123')),
        'ssw.test/api/integrasi/ssw-master-izin*' => Http::response(['success' => true, 'data' => []]),
    ]);

    (new SswClient(config('ssw')))->masterIzin();

    Http::assertSent(fn (Request $r) => str_contains($r->url(), 'ssw-master-izin')
        && $r->hasHeader('Authorization', 'Bearer ABC123')
        && str_contains($r->url(), 'id_layanan=36'));
});

it('login ulang sekali saat token ditolak dengan 401', function () {
    $izin = 0;

    Http::fake([
        'ssw.test/api/login' => Http::sequence()
            ->push(sswResponseLogin('TOKEN-LAMA'))
            ->push(sswResponseLogin('TOKEN-BARU')),
        'ssw.test/api/integrasi/ssw-master-izin*' => function () use (&$izin) {
            $izin++;

            return $izin === 1
                ? Http::response(['message' => 'Unauthenticated.'], 401)
                : Http::response(['success' => true, 'data' => [['id_ijin' => 9000]]]);
        },
    ]);

    $hasil = (new SswClient(config('ssw')))->masterIzin();

    expect($hasil)->toHaveCount(1);
    Http::assertSent(fn (Request $r) => str_contains($r->url(), 'ssw-master-izin')
        && $r->hasHeader('Authorization', 'Bearer TOKEN-BARU'));
});

it('melempar SswAuthenticationException saat login ditolak', function () {
    Http::fake(['ssw.test/api/login' => Http::response(['message' => 'Unauthorized'], 401)]);

    expect(fn () => (new SswClient(config('ssw')))->login())
        ->toThrow(SswAuthenticationException::class);
});

/* =========================================================================
 * Master izin
 * ========================================================================= */

it('menyusun opsi izin id_ijin => nama perizinan dan meng-cache-nya', function () {
    Http::fake([
        'ssw.test/api/login' => Http::response(sswResponseLogin()),
        'ssw.test/api/integrasi/ssw-master-izin*' => Http::response([
            'success' => true,
            'data' => [
                ['id_layanan' => 36, 'id_ijin' => 9000, 'nama_perizinan' => 'MPPD Nakes'],
                ['id_layanan' => 36, 'id_ijin' => 9100, 'nama_perizinan' => 'Akupuntur'],
            ],
        ]),
    ]);

    $ssw = sswService();

    expect($ssw->opsiIzin())->toBe([9000 => 'MPPD Nakes', 9100 => 'Akupuntur']);

    $ssw->opsiIzin(); // dari cache

    Http::assertSentCount(2); // login + 1x master izin saja
    expect($ssw->cariIzin(9100)['nama_perizinan'])->toBe('Akupuntur');
});

/* =========================================================================
 * Pemetaan payload
 * ========================================================================= */

it('memetakan record SIP ke payload sesuai kontrak SSW', function () {
    config()->set('ssw.defaults.id_ijin', 9000);

    $payload = SswMppdPayload::dariRecord(sswRecord());

    expect($payload)->toMatchArray([
        'nomor_str' => 'STR-35780122032025-001',
        'masa_berlaku_str' => '5 Tahun',
        'nomor_register' => 'REG-2025-0012345',
        'profesi' => 'Dokter Umum',
        'tempat_praktik' => 'Klinik Sehat Sentosa',
        'nomor_sip' => 'SIP-503.445/2025/DINKES',
        'tanggal_terbit_sip' => '2025-09-09',
        'tanggal_akhir_sip' => '2030-09-08',
        'id_ijin' => 9000,
        'jenis_permohonan' => 'Baru',
        'no_identitas' => '3578012203250001', // titik dibuang, tipe Numeric
        'nama' => 'Budi Santoso',
        'no_hp' => '6281234567890',           // 0812… → 62812…
        'email' => 'budi@example.test',
    ]);

    expect($payload['link_syarat'])->toContain('/sip/upload/REG-2025-0012345');
    expect(array_keys($payload))->toBe([
        'nomor_str', 'masa_berlaku_str', 'nomor_register', 'profesi',
        'tempat_praktik', 'alamat_tempat_praktik', 'nomor_sip',
        'tanggal_terbit_sip', 'tanggal_akhir_sip', 'link_syarat', 'keterangan',
        'id_ijin', 'jenis_permohonan', 'no_identitas', 'nama', 'no_hp',
        'email', 'alamat',
    ]);
});

it('mengutamakan id_ijin & jenis_permohonan milik record daripada default config', function () {
    config()->set('ssw.defaults.id_ijin', 9000);
    config()->set('ssw.defaults.jenis_permohonan', 'Baru');

    $payload = SswMppdPayload::dariRecord(sswRecord([
        'id_ijin' => 9101,
        'jenis_permohonan' => 'Perpanjangan',
    ]));

    expect($payload['id_ijin'])->toBe(9101)
        ->and($payload['jenis_permohonan'])->toBe('Perpanjangan');
});

it('menerima override dan mengabaikan key yang tidak dikenal SSW', function () {
    $payload = SswMppdPayload::dariRecord(sswRecord(), [
        'id_ijin' => 9101,
        'jenis_permohonan' => 'Perpanjangan',
        'status' => 'selesai', // bukan field SSW
    ]);

    expect($payload['id_ijin'])->toBe(9101)
        ->and($payload['jenis_permohonan'])->toBe('Perpanjangan')
        ->and($payload)->not->toHaveKey('status');
});

it('menolak payload tanpa id_ijin sebelum menyentuh jaringan', function () {
    config()->set('ssw.defaults.id_ijin', null);
    Http::fake();

    expect(fn () => SswMppdPayload::dariRecord(sswRecord()))
        ->toThrow(SswValidationException::class, 'ID Izin SSW');

    Http::assertNothingSent();
});

/* =========================================================================
 * Gerbang kirim
 * ========================================================================= */

it('hanya mengizinkan berkas berstatus proses yang belum terkirim', function () {
    config()->set('ssw.defaults.id_ijin', 9000);

    $ssw = sswService();

    expect($ssw->bolehDikirim(sswRecord(['status' => 'proses'])))->toBeTrue()
        ->and($ssw->bolehDikirim(sswRecord(['status' => 'masuk'])))->toBeFalse()
        ->and($ssw->bolehDikirim(sswRecord(['status' => 'menunggu_verifikasi_teknis'])))->toBeFalse()
        ->and($ssw->bolehDikirim(sswRecord(['status' => 'ditolak'])))->toBeFalse();
});

it('menolak berkas yang sudah pernah terkirim ke SSW', function () {
    config()->set('ssw.defaults.id_ijin', 9000);

    $record = sswRecord(['status' => 'proses', 'ssw_dikirim_pada' => now()]);

    expect(sswService()->bolehDikirim($record))->toBeFalse();
});

it('menolak berkas tanpa id_ijin ketika tidak ada default', function () {
    config()->set('ssw.defaults.id_ijin', null);

    expect(sswService()->bolehDikirim(sswRecord(['status' => 'proses'])))->toBeFalse()
        ->and(sswService()->bolehDikirim(sswRecord(['status' => 'proses', 'id_ijin' => 9100])))->toBeTrue();
});

it('mengikuti daftar status yang dikonfigurasi', function () {
    config()->set('ssw.defaults.id_ijin', 9000);
    config()->set('ssw.status.boleh_kirim', ['selesai']);

    $ssw = sswService();

    expect($ssw->bolehDikirim(sswRecord(['status' => 'selesai'])))->toBeTrue()
        ->and($ssw->bolehDikirim(sswRecord(['status' => 'proses'])))->toBeFalse();
});

/* =========================================================================
 * Kirim MPPD
 * ========================================================================= */

it('mengirim data MPPD sebagai multipart dan mengembalikan id permohonan', function () {
    config()->set('ssw.defaults.id_ijin', 9000);

    Http::fake([
        'ssw.test/api/login' => Http::response(sswResponseLogin()),
        'ssw.test/api/integrasi/ssw-mppd' => Http::response([
            'success' => true,
            'data' => ['id_t_permohonan_det' => 1234, 'id_dinkes_mppd_det' => 1, 'nomor_register' => 'REG-2025-0012345'],
            'message' => 'Data berhasil ditambahkan',
        ]),
    ]);

    $hasil = sswService()->kirim(sswRecord());

    expect($hasil->idPermohonanDetail())->toBe(1234)
        ->and($hasil->idDinkesMppdDetail())->toBe(1)
        ->and($hasil->nomorRegister())->toBe('REG-2025-0012345');

    Http::assertSent(function (Request $r) {
        if (! str_ends_with($r->url(), '/integrasi/ssw-mppd')) {
            return false;
        }

        $field = collect($r->data())->pluck('contents', 'name');

        return str_contains($r->header('Content-Type')[0] ?? '', 'multipart/form-data')
            && $field['nomor_register'] === 'REG-2025-0012345'
            && $field['id_ijin'] === '9000'
            && $field['tanggal_terbit_sip'] === '2025-09-09';
    });
});

it('mengirim sebagai JSON bila request_format json', function () {
    config()->set('ssw.request_format', 'json');
    config()->set('ssw.defaults.id_ijin', 9000);

    Http::fake([
        'ssw.test/api/login' => Http::response(sswResponseLogin()),
        'ssw.test/api/integrasi/ssw-mppd' => Http::response(['success' => true, 'data' => ['id_t_permohonan_det' => 7]]),
    ]);

    sswService()->kirim(sswRecord());

    Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/integrasi/ssw-mppd')
        && $r->data()['id_ijin'] === 9000);
});

it('melempar SswRequestException lengkap dengan pesan dari SSW', function () {
    config()->set('ssw.defaults.id_ijin', 9000);

    Http::fake([
        'ssw.test/api/login' => Http::response(sswResponseLogin()),
        'ssw.test/api/integrasi/ssw-mppd' => Http::response([
            'success' => false,
            'message' => 'Nomor register sudah terdaftar',
            'errors' => ['nomor_register' => ['sudah dipakai']],
        ], 422),
    ]);

    try {
        sswService()->kirim(sswRecord());
        $this->fail('Seharusnya melempar SswRequestException');
    } catch (SswRequestException $e) {
        expect($e->status)->toBe(422)
            ->and($e->getMessage())->toContain('Nomor register sudah terdaftar')
            ->and($e->errorValidasi())->toHaveKey('nomor_register');
    }
});

it('tidak mengirim apa pun ketika integrasi dimatikan', function () {
    config()->set('ssw.enabled', false);
    Http::fake();

    expect(fn () => sswService()->kirim(sswRecord()))->toThrow(SswDisabledException::class);

    Http::assertNothingSent();
});
