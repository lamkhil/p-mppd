<?php

namespace App\Jobs;

use App\Models\SuratIzinPraktik;
use App\Services\Ssw\Exceptions\SswDisabledException;
use App\Services\Ssw\Exceptions\SswValidationException;
use App\Services\Ssw\SswMppdService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Throwable;

/**
 * Kirim satu record SIP ke SSW di background.
 *
 * Dipakai untuk pengiriman massal / otomatis. Untuk aksi manual satu record
 * di panel, panggil SswMppdService::kirim() langsung supaya petugas melihat
 * hasilnya seketika.
 */
class KirimMppdKeSsw implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** Jeda antar percobaan: 1 menit, 5 menit, 15 menit. */
    public array $backoff = [60, 300, 900];

    /** @param array<string, mixed> $override */
    public function __construct(
        public SuratIzinPraktik $record,
        public array $override = [],
    ) {}

    /** @return array<int, object> */
    public function middleware(): array
    {
        // Satu record tidak boleh dikirim dua kali bersamaan.
        return [(new WithoutOverlapping('ssw-mppd:'.$this->record->getKey()))->dontRelease()];
    }

    public function handle(SswMppdService $ssw): void
    {
        try {
            $hasil = $ssw->kirimDanCatat($this->record, $this->override);
        } catch (SswDisabledException|SswValidationException $e) {
            // Integrasi dimatikan atau data belum lengkap — mengulang tidak
            // akan mengubah apa pun, jadi langsung tandai gagal.
            $this->fail($e);

            return;
        }

        activity()
            ->performedOn($this->record)
            ->withProperties($hasil->toArray())
            ->log('Data dikirim ke SSW');
    }

    public function failed(?Throwable $e): void
    {
        activity()
            ->performedOn($this->record)
            ->withProperties([
                'error' => $e?->getMessage(),
                'tipe' => $e ? class_basename($e) : null,
            ])
            ->log('Gagal mengirim data ke SSW');
    }

    /** @return array<int, string> */
    public function tags(): array
    {
        return ['ssw', 'sip:'.$this->record->nomor_register];
    }
}
