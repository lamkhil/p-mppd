<?php

namespace App\Services\Ssw\Exceptions;

/** Integrasi dimatikan lewat `ssw.enabled` / SSW_ENABLED. */
class SswDisabledException extends SswException
{
    public static function make(): self
    {
        return new self('Integrasi SSW sedang dinonaktifkan (SSW_ENABLED=false).');
    }

    public function pesanUntukPengguna(): string
    {
        return 'Integrasi SSW sedang dinonaktifkan. Hubungi administrator.';
    }
}
