<?php

namespace App\Services\Ssw\Exceptions;

use RuntimeException;

/**
 * Induk seluruh kegagalan integrasi SSW.
 *
 * Tangkap kelas ini kalau pemanggil hanya peduli "integrasi gagal" tanpa
 * membedakan penyebabnya.
 */
class SswException extends RuntimeException
{
    /**
     * Pesan siap tampil ke petugas (Bahasa Indonesia, tanpa detail teknis).
     */
    public function pesanUntukPengguna(): string
    {
        return $this->getMessage();
    }
}
