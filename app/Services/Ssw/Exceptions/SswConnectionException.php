<?php

namespace App\Services\Ssw\Exceptions;

use Throwable;

/** Tidak bisa menjangkau server SSW (timeout, DNS, TLS). */
class SswConnectionException extends SswException
{
    public static function dari(Throwable $e, string $konteks): self
    {
        return new self(
            sprintf('%s gagal: tidak dapat terhubung ke SSW (%s)', $konteks, $e->getMessage()),
            0,
            $e
        );
    }

    public function pesanUntukPengguna(): string
    {
        return 'Server SSW tidak dapat dihubungi. Coba lagi beberapa saat lagi.';
    }
}
