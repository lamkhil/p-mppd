<?php

namespace App\Console\Commands;

use App\Services\Ssw\Exceptions\SswException;
use App\Services\Ssw\SswMppdService;
use Illuminate\Console\Command;

class SswMasterIzinCommand extends Command
{
    protected $signature = 'ssw:izin
        {--segar : Ambil ulang dari SSW, abaikan cache}
        {--json : Tampilkan sebagai JSON mentah}';

    protected $description = 'Tampilkan daftar id_ijin layanan MPPD dari SSW';

    public function handle(SswMppdService $ssw): int
    {
        try {
            $izin = $ssw->daftarIzin(segar: (bool) $this->option('segar'));
        } catch (SswException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        if ($izin->isEmpty()) {
            $this->components->warn('SSW tidak mengembalikan satu pun izin untuk id_layanan '.config('ssw.id_layanan').'.');

            return self::SUCCESS;
        }

        if ($this->option('json')) {
            $this->line($izin->toJson(JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }

        $this->table(
            ['id_ijin', 'Nama Perizinan', 'Dinas', 'Layanan', 'Lama (menit)'],
            $izin->map(fn ($i) => [
                $i['id_ijin'] ?? '-',
                $i['nama_perizinan'] ?? '-',
                $i['nama_dinas'] ?? '-',
                $i['nama_layanan'] ?? '-',
                $i['lama_menit'] ?? '-',
            ])->all()
        );

        $this->components->info($izin->count().' izin ditemukan.');

        return self::SUCCESS;
    }
}
