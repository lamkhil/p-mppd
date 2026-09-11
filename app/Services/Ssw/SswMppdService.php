<?php

namespace App\Services\Ssw;

use App\Models\SuratIzinPraktik;
use App\Services\Ssw\Exceptions\SswDisabledException;
use App\Services\Ssw\Exceptions\SswException;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Pintu masuk aplikasi ke integrasi SSW.
 *
 * Ini yang dipakai controller / Filament action / job — bukan SswClient
 * langsung, supaya sakelar `ssw.enabled`, cache master izin, dan pemetaan
 * payload konsisten di semua tempat.
 *
 * Semua method pengirim melempar turunan SswException bila gagal; tidak ada
 * yang mengembalikan false diam-diam.
 */
class SswMppdService
{
    /** @param array<string, mixed> $config */
    public function __construct(
        protected SswClient $client,
        protected array $config,
    ) {}

    public function client(): SswClient
    {
        return $this->client;
    }

    public function aktif(): bool
    {
        return (bool) ($this->config['enabled'] ?? false);
    }

    /* =====================================================================
     * Master izin
     * ===================================================================== */

    /**
     * Daftar izin layanan MPPD (id_layanan 36), di-cache karena hampir
     * tidak pernah berubah.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function daftarIzin(bool $segar = false): Collection
    {
        $this->pastikanAktif();

        $key = (string) ($this->config['cache']['master_izin_key'] ?? 'ssw:master-izin');
        $ttl = (int) ($this->config['cache']['master_izin_ttl'] ?? 86400);

        if ($segar) {
            $this->cache()->forget($key);
        }

        $data = $this->cache()->remember($key, $ttl, fn () => $this->client->masterIzin());

        return collect($data);
    }

    /**
     * Opsi siap pakai untuk Select Filament: id_ijin => "Nama Perizinan".
     *
     * @return array<int, string>
     */
    public function opsiIzin(bool $segar = false): array
    {
        return $this->daftarIzin($segar)
            ->filter(fn ($izin) => isset($izin['id_ijin']))
            ->mapWithKeys(fn ($izin) => [
                (int) $izin['id_ijin'] => (string) ($izin['nama_perizinan'] ?? $izin['id_ijin']),
            ])
            ->all();
    }

    /**
     * Versi `opsiIzin()` untuk UI: kalau SSW mati / kredensial salah /
     * integrasi dimatikan, form tetap bisa dibuka dengan opsi kosong alih-alih
     * melempar exception di tengah render halaman.
     *
     * @return array<int, string>
     */
    public function opsiIzinAman(bool $segar = false): array
    {
        // Integrasi dimatikan bukan kondisi error — jangan penuhi log.
        if (! $this->aktif()) {
            return [];
        }

        try {
            return $this->opsiIzin($segar);
        } catch (SswException $e) {
            Log::channel($this->config['log_channel'] ?? null)
                ->warning('[SSW] Gagal memuat opsi izin untuk form: '.$e->getMessage());

            return [];
        }
    }

    /** @return array<string, mixed>|null */
    public function cariIzin(int|string $idIjin, bool $segar = false): ?array
    {
        return $this->daftarIzin($segar)
            ->first(fn ($izin) => (string) ($izin['id_ijin'] ?? '') === (string) $idIjin);
    }

    public function lupakanCacheIzin(): void
    {
        $this->cache()->forget((string) ($this->config['cache']['master_izin_key'] ?? 'ssw:master-izin'));
    }

    /* =====================================================================
     * Kirim data MPPD
     * ===================================================================== */

    /**
     * Rakit payload dari record tanpa mengirim apa pun — untuk pratinjau,
     * dry-run, dan pengujian kelengkapan data.
     *
     * @param  array<string, mixed>  $override
     * @return array<string, mixed>
     */
    public function pratinjau(SuratIzinPraktik $record, array $override = []): array
    {
        return SswMppdPayload::dariRecord($record, $override);
    }

    /**
     * Kirim satu record SIP ke SSW.
     *
     * @param  array<string, mixed>  $override  mis. ['id_ijin' => 9100]
     *
     * @throws \App\Services\Ssw\Exceptions\SswException
     */
    public function kirim(SuratIzinPraktik $record, array $override = []): SswMppdResult
    {
        $this->pastikanAktif();

        return $this->kirimPayload(SswMppdPayload::dariRecord($record, $override));
    }

    /**
     * Kirim lalu simpan bukti sinkronisasi ke record.
     *
     * Ini yang sebaiknya dipakai semua pemanggil di aplikasi — kalau
     * penulisan kolom `ssw_*` dikerjakan sendiri-sendiri di tiap action,
     * cepat atau lambat ada jalur yang lupa menulisnya.
     *
     * Kegagalan tetap dilempar (setelah pesannya dicatat di `ssw_error`).
     *
     * @param  array<string, mixed>  $override
     *
     * @throws \App\Services\Ssw\Exceptions\SswException
     */
    public function kirimDanCatat(SuratIzinPraktik $record, array $override = []): SswMppdResult
    {
        try {
            $hasil = $this->kirim($record, $override);
        } catch (SswException $e) {
            $record->update(['ssw_error' => $e->getMessage()]);

            throw $e;
        }

        $record->update([
            'ssw_id_permohonan_det' => $hasil->idPermohonanDetail(),
            'ssw_id_dinkes_mppd_det' => $hasil->idDinkesMppdDetail(),
            'ssw_dikirim_pada' => now(),
            'ssw_error' => null,
            // Berkas mengendap di sini tanpa aksi apa pun sampai dinas teknis
            // mengirim keputusannya lewat webhook.
            'status' => $this->config['status']['setelah_kirim'] ?? 'menunggu_verifikasi_teknis',
        ]);

        return $hasil;
    }

    /**
     * Kirim payload yang sudah dirakit sendiri (tetap divalidasi kelengkapannya).
     *
     * @param  array<string, mixed>  $payload
     *
     * @throws \App\Services\Ssw\Exceptions\SswException
     */
    public function kirimPayload(array $payload): SswMppdResult
    {
        $this->pastikanAktif();

        SswMppdPayload::pastikanLengkap($payload);

        $data = $this->client->insertMppd($payload);

        return new SswMppdResult(
            data: $data,
            payload: $payload,
            message: 'Data berhasil dikirim ke SSW.',
        );
    }

    /**
     * Status apa saja yang boleh dikirim ke SSW.
     *
     * @return array<int, string>
     */
    public function statusBolehKirim(): array
    {
        return (array) ($this->config['status']['boleh_kirim'] ?? ['proses']);
    }

    /**
     * Kelayakan satu record untuk dikirim — dipakai action di halaman View
     * maupun penyaringan bulk action, supaya aturannya tidak bercabang dua.
     */
    public function bolehDikirim(SuratIzinPraktik $record): bool
    {
        return $this->aktif()
            && in_array($record->status, $this->statusBolehKirim(), true)
            && ! $record->sudahTerkirimKeSsw()
            && (filled($record->id_ijin) || filled($this->config['defaults']['id_ijin'] ?? null));
    }

    /* =====================================================================
     * Internal
     * ===================================================================== */

    protected function pastikanAktif(): void
    {
        if (! $this->aktif()) {
            throw SswDisabledException::make();
        }
    }

    protected function cache(): CacheRepository
    {
        $store = $this->config['cache']['store'] ?? null;

        return $store ? Cache::store($store) : Cache::store();
    }
}
