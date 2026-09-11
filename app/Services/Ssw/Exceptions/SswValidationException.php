<?php

namespace App\Services\Ssw\Exceptions;

/** Payload MPPD tidak lengkap sebelum dikirim ke SSW. */
class SswValidationException extends SswException
{
    /** @param array<int, string> $fieldKosong */
    public function __construct(public readonly array $fieldKosong)
    {
        parent::__construct(
            'Data belum lengkap untuk dikirim ke SSW: '.implode(', ', $fieldKosong).'.'
        );
    }

    public function pesanUntukPengguna(): string
    {
        return 'Data belum lengkap: '.implode(', ', $this->fieldKosong).'.';
    }
}
