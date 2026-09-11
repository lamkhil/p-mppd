<?php

namespace App\Services\Ssw\Exceptions;

use Illuminate\Http\Client\Response;
use Throwable;

/**
 * SSW membalas dengan status error, `success: false`, atau body yang tidak
 * bisa dibaca. Menyimpan status & body mentah supaya bisa dicatat/ditampilkan.
 */
class SswRequestException extends SswException
{
    /** @param array<string, mixed>|null $body */
    public function __construct(
        string $message,
        public readonly ?int $status = null,
        public readonly ?array $body = null,
        public readonly ?string $rawBody = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $status ?? 0, $previous);
    }

    public static function dariResponse(Response $response, string $konteks): self
    {
        $body = null;

        try {
            $decoded = $response->json();
            $body = is_array($decoded) ? $decoded : null;
        } catch (Throwable) {
            // biarkan null — rawBody tetap disimpan
        }

        $pesan = is_array($body)
            ? ($body['message'] ?? $body['error'] ?? null)
            : null;

        if (is_array($pesan)) {
            $pesan = implode(' ', array_map(
                fn ($v) => is_array($v) ? implode(' ', $v) : (string) $v,
                $pesan
            ));
        }

        return new self(
            sprintf(
                '%s gagal (HTTP %d)%s',
                $konteks,
                $response->status(),
                $pesan ? ': '.$pesan : '.'
            ),
            $response->status(),
            $body,
            mb_substr((string) $response->body(), 0, 2000),
        );
    }

    public function pesanUntukPengguna(): string
    {
        $pesan = $this->body['message'] ?? null;

        return is_string($pesan) && $pesan !== ''
            ? 'SSW menolak permintaan: '.$pesan
            : 'SSW menolak permintaan (HTTP '.($this->status ?? '-').').';
    }

    /** Errors validasi dari SSW, kalau ada, dalam bentuk field => pesan. */
    public function errorValidasi(): array
    {
        $errors = $this->body['errors'] ?? null;

        return is_array($errors) ? $errors : [];
    }
}
