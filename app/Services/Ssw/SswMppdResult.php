<?php

namespace App\Services\Ssw;

/**
 * Hasil sukses pengiriman data MPPD ke SSW.
 *
 * `data` adalah node `data` mentah dari response SSW, `payload` adalah yang
 * benar-benar dikirim — keduanya disimpan supaya bisa dicatat ke activity log
 * atau kolom sinkronisasi tanpa memanggil ulang.
 */
readonly class SswMppdResult
{
    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public array $data,
        public array $payload,
        public ?string $message = null,
    ) {}

    /** ID detail permohonan di sisi SSW — simpan ini sebagai bukti sinkronisasi. */
    public function idPermohonanDetail(): int|string|null
    {
        return $this->data['id_t_permohonan_det'] ?? null;
    }

    public function idDinkesMppdDetail(): int|string|null
    {
        return $this->data['id_dinkes_mppd_det'] ?? null;
    }

    public function nomorRegister(): ?string
    {
        $nomor = $this->data['nomor_register'] ?? $this->payload['nomor_register'] ?? null;

        return $nomor === null ? null : (string) $nomor;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id_t_permohonan_det' => $this->idPermohonanDetail(),
            'id_dinkes_mppd_det' => $this->idDinkesMppdDetail(),
            'nomor_register' => $this->nomorRegister(),
            'message' => $this->message,
            'data' => $this->data,
        ];
    }
}
