<?php

namespace App\Console\Commands;

use App\Services\Ssw\Exceptions\SswException;
use App\Services\Ssw\SswClient;
use Illuminate\Console\Command;

class SswLoginCommand extends Command
{
    protected $signature = 'ssw:login {--segar : Abaikan token di cache, paksa login ulang}';

    protected $description = 'Uji kredensial SSW dan tampilkan status token';

    public function handle(SswClient $client): int
    {
        try {
            $token = $client->token(paksaBaru: (bool) $this->option('segar'));
        } catch (SswException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $this->components->info('Token SSW siap dipakai.');
        $this->line('  Base URL : '.config('ssw.base_url'));
        $this->line('  Token    : '.substr($token, 0, 8).'…'.substr($token, -4));

        return self::SUCCESS;
    }
}
