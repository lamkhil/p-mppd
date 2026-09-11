<?php

namespace App\Services\Ssw;

use App\Models\SuratIzinPraktik;
use App\Services\Ssw\Exceptions\SswValidationException;
use Carbon\CarbonInterface;
use DateTimeInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Pemetaan record SuratIzinPraktik → payload POST /integrasi/ssw-mppd.
 *
 * Urutan & nama key mengikuti dokumentasi SSW persis; jangan diubah tanpa
 * dokumentasi baru dari SSW. Nilai yang tidak ada di tabel `surat_izin_praktik`
 * (id_ijin, jenis_permohonan, link_syarat) diambil dari — berurutan —
 * override pemanggil, atribut record (kalau kolomnya nanti ditambahkan),
 * lalu default di `config/ssw.php`.
 */
class SswMppdPayload
{
    /** Field yang tanpa itu SSW hampir pasti menolak permohonan. */
    public const WAJIB = [
        'nomor_register',
        'nama',
        'no_identitas',
        'id_ijin',
        'jenis_permohonan',
    ];

    /**
     * @param  array<string, mixed>  $override  nilai yang menimpa hasil pemetaan
     * @return array<string, mixed>
     *
     * @throws SswValidationException
     */
    public static function dariRecord(SuratIzinPraktik $record, array $override = []): array
    {
        $config = config('ssw.defaults', []);

        $payload = [
            'nomor_str' => static::teks($record->nomor_str),
            'masa_berlaku_str' => static::teks($record->masa_berlaku_str),
            'nomor_register' => static::teks($record->nomor_register),
            'profesi' => static::teks($record->profesi),
            'tempat_praktik' => static::teks($record->tempat_praktik),
            'alamat_tempat_praktik' => static::teks($record->alamat_tempat_praktik),
            'nomor_sip' => static::teks($record->nomor_sip),
            'tanggal_terbit_sip' => static::tanggal($record->tanggal_terbit_sip),
            'tanggal_akhir_sip' => static::tanggal($record->tanggal_akhir_sip),
            'link_syarat' => static::linkSyarat($record, $config['link_syarat'] ?? null),
            'keterangan' => static::teks($record->keterangan),
            'id_ijin' => static::idIjin($record->id_ijin ?? null, $config['id_ijin'] ?? null),
            'jenis_permohonan' => static::teks($record->jenis_permohonan ?? null)
                ?? static::teks($config['jenis_permohonan'] ?? null),
            'no_identitas' => static::angka($record->nik),
            'nama' => static::teks($record->nama),
            'no_hp' => static::nomorHp($record->nomor_telepon),
            'email' => static::teks($record->email),
            'alamat' => static::teks($record->alamat),
        ];

        $payload = array_merge($payload, static::bersihkanOverride($override));

        static::pastikanLengkap($payload);

        return $payload;
    }

    /**
     * Validasi payload apa adanya (dipakai juga kalau payload dirakit manual).
     *
     * @param  array<string, mixed>  $payload
     *
     * @throws SswValidationException
     */
    public static function pastikanLengkap(array $payload): void
    {
        $kosong = [];

        foreach (static::WAJIB as $field) {
            if (blank($payload[$field] ?? null)) {
                $kosong[] = static::labelField($field);
            }
        }

        if ($kosong !== []) {
            throw new SswValidationException($kosong);
        }
    }

    /* =====================================================================
     * Normalisasi nilai
     * ===================================================================== */

    protected static function teks(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    /** Tanggal selalu Y-m-d sesuai catatan implementasi SSW. */
    protected static function tanggal(mixed $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        if ($value instanceof DateTimeInterface || $value instanceof CarbonInterface) {
            return Carbon::instance($value)->format('Y-m-d');
        }

        try {
            return Carbon::parse((string) $value)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    /** SSW meminta tipe Numeric → buang semua karakter non-digit. */
    protected static function angka(mixed $value): ?string
    {
        $digit = preg_replace('/\D/', '', (string) $value);

        return blank($digit) ? null : $digit;
    }

    /** 08xx → 628xx, +62 → 62, spasi/strip dibuang. */
    protected static function nomorHp(mixed $value): ?string
    {
        $digit = static::angka($value);

        if ($digit === null) {
            return null;
        }

        if (Str::startsWith($digit, '0')) {
            return '62'.ltrim(substr($digit, 1), '0');
        }

        return $digit;
    }

    protected static function idIjin(mixed $dariRecord, mixed $default): ?int
    {
        foreach ([$dariRecord, $default] as $kandidat) {
            if (filled($kandidat) && is_numeric($kandidat)) {
                return (int) $kandidat;
            }
        }

        return null;
    }

    /**
     * Halaman upload dokumen milik record ini dipakai sebagai `link_syarat`
     * kalau tidak ada URL khusus yang dikonfigurasi — di situlah pemohon
     * melihat & melengkapi syaratnya.
     */
    protected static function linkSyarat(SuratIzinPraktik $record, mixed $default): ?string
    {
        if (filled($default)) {
            return (string) $default;
        }

        if (blank($record->nomor_register)) {
            return null;
        }

        return route('sip.upload', ['record' => $record->getRouteKey()]);
    }

    /**
     * Override boleh berisi null untuk "kosongkan", tapi key yang tidak
     * dikenal SSW dibuang supaya payload tetap sesuai kontrak.
     *
     * @param  array<string, mixed>  $override
     * @return array<string, mixed>
     */
    protected static function bersihkanOverride(array $override): array
    {
        $dikenal = [
            'nomor_str', 'masa_berlaku_str', 'nomor_register', 'profesi',
            'tempat_praktik', 'alamat_tempat_praktik', 'nomor_sip',
            'tanggal_terbit_sip', 'tanggal_akhir_sip', 'link_syarat',
            'keterangan', 'id_ijin', 'jenis_permohonan', 'no_identitas',
            'nama', 'no_hp', 'email', 'alamat',
        ];

        return array_intersect_key($override, array_flip($dikenal));
    }

    protected static function labelField(string $field): string
    {
        return match ($field) {
            'nomor_register' => 'Nomor Register',
            'nama' => 'Nama',
            'no_identitas' => 'NIK',
            'id_ijin' => 'ID Izin SSW',
            'jenis_permohonan' => 'Jenis Permohonan',
            default => Str::headline($field),
        };
    }
}
