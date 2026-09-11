<?php

namespace App\Services\Ssw;

use App\Services\Ssw\Exceptions\SswAuthenticationException;
use App\Services\Ssw\Exceptions\SswConnectionException;
use App\Services\Ssw\Exceptions\SswRequestException;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Client mentah untuk Web Service SSW (kantorku.surabaya.go.id).
 *
 * Tanggung jawabnya hanya transport: login, menyimpan Bearer token, dan
 * memanggil endpoint. Tidak tahu apa-apa soal model SuratIzinPraktik —
 * pemetaan data ada di SswMppdPayload, orkestrasi di SswMppdService.
 *
 * Alur token: token hasil /api/login disimpan di cache. Setiap request
 * memakai token itu; kalau SSW membalas 401, token dibuang lalu request
 * diulang sekali dengan token baru (dokumentasi SSW tidak menjelaskan
 * mekanisme refresh, jadi re-login adalah satu-satunya jalan).
 */
class SswClient
{
    /** @param array<string, mixed> $config */
    public function __construct(protected array $config) {}

    /* =====================================================================
     * Autentikasi
     * ===================================================================== */

    /**
     * Login ke SSW dan kembalikan token baru (sekaligus menyimpannya di cache).
     *
     * @throws SswAuthenticationException|SswConnectionException
     */
    public function login(): string
    {
        $username = $this->config['username'] ?? null;
        $password = $this->config['password'] ?? null;

        if (blank($username) || blank($password)) {
            throw new SswAuthenticationException(
                'Kredensial SSW belum diisi (SSW_USERNAME / SSW_PASSWORD).'
            );
        }

        $response = $this->kirimMentah(
            fn () => $this->pending()->asJson()->post($this->url('login'), [
                'username' => $username,
                'password' => $password,
            ]),
            'Login SSW'
        );

        if (! $response->successful()) {
            throw new SswAuthenticationException(
                SswRequestException::dariResponse($response, 'Login SSW')->getMessage()
            );
        }

        $token = $response->json('token');

        if (! is_string($token) || $token === '') {
            throw new SswAuthenticationException(
                'Login SSW berhasil tetapi response tidak memuat token.'
            );
        }

        $this->cache()->put($this->tokenKey(), $token, $this->config['cache']['token_ttl'] ?? 1800);

        $this->log('info', 'Login SSW berhasil', [
            'user' => $response->json('user.username'),
            'kode_instansi' => $response->json('user.kode_instansi'),
            'expired' => $response->json('user.expired'),
        ]);

        return $token;
    }

    /** Token yang tersimpan, atau login baru kalau belum/tidak ada. */
    public function token(bool $paksaBaru = false): string
    {
        if ($paksaBaru) {
            $this->lupakanToken();

            return $this->login();
        }

        $token = $this->cache()->get($this->tokenKey());

        return is_string($token) && $token !== '' ? $token : $this->login();
    }

    public function lupakanToken(): void
    {
        $this->cache()->forget($this->tokenKey());
    }

    /* =====================================================================
     * Endpoint
     * ===================================================================== */

    /**
     * GET /integrasi/ssw-master-izin?id_layanan=36
     *
     * @return array<int, array<string, mixed>> daftar izin apa adanya dari SSW
     */
    public function masterIzin(?int $idLayanan = null): array
    {
        $response = $this->kirimTerautentikasi(
            fn (PendingRequest $http) => $http->get($this->url('master_izin'), [
                'id_layanan' => $idLayanan ?? (int) ($this->config['id_layanan'] ?? 36),
            ]),
            'Ambil master izin SSW'
        );

        $data = $this->ambilData($response, 'Ambil master izin SSW');

        return array_values(array_filter($data, 'is_array'));
    }

    /**
     * POST /integrasi/ssw-mppd
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed> node `data` dari response SSW
     */
    public function insertMppd(array $payload): array
    {
        $response = $this->kirimTerautentikasi(
            fn (PendingRequest $http) => $this->postDenganFormat($http, $this->url('mppd'), $payload),
            'Kirim data MPPD'
        );

        $data = $this->ambilData($response, 'Kirim data MPPD');

        return $data;
    }

    /* =====================================================================
     * Internal
     * ===================================================================== */

    public function url(string $endpoint): string
    {
        $path = $this->config['endpoints'][$endpoint] ?? $endpoint;

        return rtrim((string) $this->config['base_url'], '/').'/'.ltrim((string) $path, '/');
    }

    /**
     * Kirim request ber-Bearer-token; ulangi sekali dengan token baru bila 401.
     *
     * @param  callable(PendingRequest): Response  $panggil
     */
    protected function kirimTerautentikasi(callable $panggil, string $konteks): Response
    {
        $token = $this->token();

        $response = $this->kirimMentah(
            fn () => $panggil($this->pending()->withToken($token)),
            $konteks
        );

        if ($response->status() === 401) {
            $this->log('warning', 'Token SSW ditolak, mencoba login ulang', ['konteks' => $konteks]);

            $token = $this->token(paksaBaru: true);

            $response = $this->kirimMentah(
                fn () => $panggil($this->pending()->withToken($token)),
                $konteks
            );

            if ($response->status() === 401) {
                throw new SswAuthenticationException(
                    $konteks.' gagal: token SSW tetap ditolak setelah login ulang.'
                );
            }
        }

        return $response;
    }

    /**
     * @param  callable(): Response  $panggil
     *
     * @throws SswConnectionException
     */
    protected function kirimMentah(callable $panggil, string $konteks): Response
    {
        try {
            $response = $panggil();
        } catch (ConnectionException $e) {
            $this->log('error', $konteks.' gagal terhubung', ['error' => $e->getMessage()]);

            throw SswConnectionException::dari($e, $konteks);
        }

        $this->log(
            $response->successful() ? 'info' : 'warning',
            $konteks.' → HTTP '.$response->status(),
            ['body' => mb_substr((string) $response->body(), 0, 1000)]
        );

        return $response;
    }

    /**
     * POST dengan format body sesuai konfigurasi (multipart / form / json).
     *
     * @param  array<string, mixed>  $payload
     */
    protected function postDenganFormat(PendingRequest $http, string $url, array $payload): Response
    {
        return match ($this->config['request_format'] ?? 'multipart') {
            'json' => $http->asJson()->post($url, $payload),
            'form' => $http->asForm()->post($url, $this->keScalar($payload)),
            default => $http->asMultipart()->post($url, $this->keMultipart($payload)),
        };
    }

    /**
     * Ambil node `data` dari response, sekaligus memvalidasi bentuk envelope
     * SSW: { success: bool, data: ..., message: string }.
     *
     * @return array<mixed>
     */
    protected function ambilData(Response $response, string $konteks): array
    {
        if (! $response->successful()) {
            throw SswRequestException::dariResponse($response, $konteks);
        }

        $json = $response->json();

        if (! is_array($json)) {
            throw new SswRequestException(
                $konteks.' gagal: response SSW bukan JSON yang valid.',
                $response->status(),
                null,
                mb_substr((string) $response->body(), 0, 2000),
            );
        }

        // `success` boleh absen (beberapa endpoint lama tidak mengirimkannya),
        // tapi kalau ada dan bernilai false, itu kegagalan.
        if (array_key_exists('success', $json) && ! $json['success']) {
            throw SswRequestException::dariResponse($response, $konteks);
        }

        $data = $json['data'] ?? null;

        if (! is_array($data)) {
            throw new SswRequestException(
                $konteks.' gagal: response SSW tidak memuat node `data`.',
                $response->status(),
                $json,
                mb_substr((string) $response->body(), 0, 2000),
            );
        }

        return $data;
    }

    protected function pending(): PendingRequest
    {
        $request = Http::acceptJson()
            ->withUserAgent('P-MPPD/'.config('app.name'))
            ->timeout((int) ($this->config['timeout'] ?? 30))
            ->connectTimeout((int) ($this->config['connect_timeout'] ?? 10))
            ->withOptions(['verify' => (bool) ($this->config['verify_ssl'] ?? true)]);

        $times = (int) ($this->config['retry']['times'] ?? 0);

        // retry() di sini hanya menangkap ConnectionException; response 4xx/5xx
        // sengaja tidak diulang supaya data tidak terkirim dua kali.
        return $times > 1
            ? $request->retry($times, (int) ($this->config['retry']['sleep'] ?? 500))
            : $request;
    }

    /**
     * Ubah payload asosiatif menjadi bentuk multipart Guzzle.
     * Nilai null dikirim sebagai string kosong karena multipart tidak
     * mengenal null.
     *
     * @param  array<string, mixed>  $payload
     * @return array<int, array{name: string, contents: string}>
     */
    protected function keMultipart(array $payload): array
    {
        $multipart = [];

        foreach ($this->keScalar($payload) as $name => $contents) {
            $multipart[] = ['name' => $name, 'contents' => $contents];
        }

        return $multipart;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, string>
     */
    protected function keScalar(array $payload): array
    {
        $hasil = [];

        foreach ($payload as $key => $value) {
            $hasil[$key] = match (true) {
                $value === null => '',
                is_bool($value) => $value ? '1' : '0',
                is_array($value) => json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                default => (string) $value,
            };
        }

        return $hasil;
    }

    protected function cache(): CacheRepository
    {
        $store = $this->config['cache']['store'] ?? null;

        return $store ? Cache::store($store) : Cache::store();
    }

    protected function tokenKey(): string
    {
        return (string) ($this->config['cache']['token_key'] ?? 'ssw:token');
    }

    /** @param array<string, mixed> $context */
    protected function log(string $level, string $message, array $context = []): void
    {
        $channel = $this->config['log_channel'] ?? null;

        Log::channel($channel)->log($level, '[SSW] '.$message, $this->mask($context));
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    protected function mask(array $context): array
    {
        array_walk_recursive($context, function (&$value, $key) {
            if (! is_string($value)) {
                return;
            }

            if (in_array((string) $key, ['password', 'token'], true)) {
                $value = '***';

                return;
            }

            // Token bisa ikut terbawa di body log response login.
            $value = preg_replace('/"token"\s*:\s*"[^"]+"/', '"token":"***"', $value);
        });

        return $context;
    }
}
