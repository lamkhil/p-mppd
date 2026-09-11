<?php

namespace App\Services\Ssw\Exceptions;

/** Callback dari SSW tidak bisa diproses. Membawa HTTP status yang pantas. */
class SswCallbackException extends SswException
{
    public function __construct(string $message, public readonly int $httpStatus = 422)
    {
        parent::__construct($message, $httpStatus);
    }

    public static function tidakDitemukan(string $nomorRegister): self
    {
        return new self("Permohonan dengan nomor register {$nomorRegister} tidak ditemukan.", 404);
    }

    public static function permohonanTidakCocok(string $nomorRegister, string $dariSsw, string $tersimpan): self
    {
        return new self(
            "id_t_permohonan_det tidak cocok untuk {$nomorRegister} (dikirim: {$dariSsw}, tersimpan: {$tersimpan}).",
            409
        );
    }

    public static function statusTidakDikenal(string $status): self
    {
        return new self("Nilai status '{$status}' tidak dikenali.", 422);
    }
}
