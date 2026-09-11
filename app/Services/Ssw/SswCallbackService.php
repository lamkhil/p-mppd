<?php

namespace App\Services\Ssw;

use App\Models\SuratIzinPraktik;
use App\Services\Ssw\Exceptions\SswCallbackException;
use Illuminate\Support\Carbon;

/**
 * Pemroses balikan verifikasi teknis dari SSW.
 *
 * Kebalikan arah dari SswMppdService: yang ini menerima keputusan dinas
 * teknis dan memindahkan berkas keluar dari status menunggu.
 *
 * Sifatnya idempoten — SSW boleh mengirim ulang callback yang sama tanpa
 * membuat perubahan ganda, karena webhook yang tidak di-retry adalah webhook
 * yang cepat atau lambat kehilangan satu keputusan.
 */
class SswCallbackService
{
    /** @param array<string, mixed> $config */
    public function __construct(protected array $config) {}

    /**
     * @param  array<string, mixed>  $payload  payload yang sudah tervalidasi
     * @return array{record: SuratIzinPraktik, duplikat: bool, status: string}
     *
     * @throws SswCallbackException
     */
    public function proses(array $payload): array
    {
        $record = SuratIzinPraktik::where('nomor_register', $payload['nomor_register'])->first();

        if (! $record) {
            throw SswCallbackException::tidakDitemukan($payload['nomor_register']);
        }

        $this->pastikanPermohonanCocok($record, $payload);

        $hasil = $this->petakanHasil($payload['status']);
        $statusBaru = $this->statusInternal($hasil);

        if ($this->sudahDiproses($record, $payload, $statusBaru)) {
            return ['record' => $record, 'duplikat' => true, 'status' => $record->status];
        }

        $record->update(array_merge([
            'status' => $statusBaru,
            'ssw_hasil_verifikasi' => $hasil,
            'ssw_diverifikasi_pada' => filled($payload['tanggal_verifikasi'] ?? null)
                ? Carbon::parse($payload['tanggal_verifikasi'])
                : now(),
            'ssw_catatan_verifikasi' => $payload['keterangan'] ?? null,
            'ssw_verifikator' => $payload['verifikator'] ?? null,
            'ssw_callback_event_id' => $payload['event_id'] ?? null,
        ], $this->dataPenerbitan($hasil, $payload)));

        activity()
            ->performedOn($record)
            ->withProperties([
                'hasil' => $hasil,
                'status_baru' => $statusBaru,
                'payload' => $payload,
            ])
            ->log('Hasil verifikasi teknis diterima dari SSW');

        return ['record' => $record, 'duplikat' => false, 'status' => $statusBaru];
    }

    /**
     * Kalau kedua sisi sama-sama tahu id permohonan SSW, keduanya harus cocok.
     * Ketidakcocokan berarti callback nyasar — lebih baik ditolak daripada
     * mengubah berkas milik orang lain.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function pastikanPermohonanCocok(SuratIzinPraktik $record, array $payload): void
    {
        $dariSsw = $payload['id_t_permohonan_det'] ?? null;

        if (blank($dariSsw) || blank($record->ssw_id_permohonan_det)) {
            return;
        }

        if ((string) $dariSsw !== (string) $record->ssw_id_permohonan_det) {
            throw SswCallbackException::permohonanTidakCocok(
                $payload['nomor_register'],
                (string) $dariSsw,
                (string) $record->ssw_id_permohonan_det,
            );
        }
    }

    /**
     * Callback ulang dikenali lewat event_id, atau — kalau SSW tidak
     * mengirimkannya — lewat kombinasi status & hasil yang sudah sama.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function sudahDiproses(SuratIzinPraktik $record, array $payload, string $statusBaru): bool
    {
        $eventId = $payload['event_id'] ?? null;

        if (filled($eventId) && (string) $record->ssw_callback_event_id === (string) $eventId) {
            return true;
        }

        return $record->status === $statusBaru
            && $record->ssw_hasil_verifikasi === $this->petakanHasil($payload['status'])
            && filled($record->ssw_diverifikasi_pada);
    }

    /** 'disetujui' atau 'ditolak' — sudah divalidasi di FormRequest. */
    protected function petakanHasil(string $status): string
    {
        return $this->config['webhook']['peta_status'][mb_strtolower($status)]
            ?? throw SswCallbackException::statusTidakDikenal($status);
    }

    protected function statusInternal(string $hasil): string
    {
        return $hasil === 'disetujui'
            ? ($this->config['status']['disetujui'] ?? 'terverifikasi_teknis')
            : ($this->config['status']['ditolak'] ?? 'ditolak_teknis');
    }

    /**
     * Nomor & masa berlaku SIP hanya diperbarui kalau SSW yang menerbitkannya
     * dan permohonan disetujui. Field yang tidak dikirim tidak disentuh.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function dataPenerbitan(string $hasil, array $payload): array
    {
        if ($hasil !== 'disetujui') {
            return [];
        }

        return array_filter([
            'nomor_sip' => $payload['nomor_sip'] ?? null,
            'tanggal_terbit_sip' => $payload['tanggal_terbit_sip'] ?? null,
            'tanggal_akhir_sip' => $payload['tanggal_akhir_sip'] ?? null,
        ], fn ($nilai) => filled($nilai));
    }
}
