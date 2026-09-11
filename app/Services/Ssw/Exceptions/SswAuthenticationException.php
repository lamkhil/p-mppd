<?php

namespace App\Services\Ssw\Exceptions;

/** Login ke SSW gagal atau token ditolak berulang kali. */
class SswAuthenticationException extends SswException
{
    public function pesanUntukPengguna(): string
    {
        return 'Gagal autentikasi ke SSW. Periksa SSW_USERNAME / SSW_PASSWORD.';
    }
}
