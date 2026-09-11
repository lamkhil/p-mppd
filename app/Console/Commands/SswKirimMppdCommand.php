<?php

namespace App\Console\Commands;

use App\Jobs\KirimMppdKeSsw;
use App\Models\SuratIzinPraktik;
use App\Services\Ssw\Exceptions\SswException;
use App\Services\Ssw\Exceptions\SswRequestException;
use App\Services\Ssw\SswMppdService;
use Illuminate\Console\Command;

class SswKirimMppdCommand extends Command
{
    protected $signature = 'ssw:kirim
        {nomor_register : Nomor register SIP yang akan dikirim}
        {--id-ijin= : Timpa id_ijin (lihat `php artisan ssw:izin`)}
        {--jenis= : Timpa jenis_permohonan}
        {--dry-run : Tampilkan payload saja, tidak mengirim}
        {--antrean : Kirim lewat queue, bukan langsung}';

    protected $description = 'Kirim satu permohonan SIP ke SSW (POST /integrasi/ssw-mppd)';

    public function handle(SswMppdService $ssw): int
    {
        $record = SuratIzinPraktik::where('nomor_register', $this->argument('nomor_register'))->first();

        if (! $record) {
            $this->components->error('Nomor register tidak ditemukan: '.$this->argument('nomor_register'));

            return self::FAILURE;
        }

        $override = array_filter([
            'id_ijin' => $this->option('id-ijin'),
            'jenis_permohonan' => $this->option('jenis'),
        ], fn ($v) => $v !== null);

        try {
            $payload = $ssw->pratinjau($record, $override);
        } catch (SswException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $this->components->twoColumnDetail('<fg=gray>Field</>', '<fg=gray>Nilai</>');
        foreach ($payload as $key => $value) {
            $this->components->twoColumnDetail($key, $value === null ? '<fg=gray>—</>' : (string) $value);
        }

        if ($this->option('dry-run')) {
            $this->components->info('Dry run — tidak ada data yang dikirim.');

            return self::SUCCESS;
        }

        if ($this->option('antrean')) {
            KirimMppdKeSsw::dispatch($record, $override);
            $this->components->info('Job pengiriman dimasukkan ke antrean.');

            return self::SUCCESS;
        }

        try {
            $hasil = $ssw->kirimPayload($payload);
        } catch (SswRequestException $e) {
            $this->components->error($e->getMessage());

            foreach ($e->errorValidasi() as $field => $pesan) {
                $this->line('  <fg=red>'.$field.'</>: '.(is_array($pesan) ? implode(', ', $pesan) : $pesan));
            }

            return self::FAILURE;
        } catch (SswException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $this->components->info('Terkirim. id_t_permohonan_det = '.($hasil->idPermohonanDetail() ?? '-'));

        return self::SUCCESS;
    }
}
