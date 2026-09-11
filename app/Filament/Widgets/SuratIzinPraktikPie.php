<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class SuratIzinPraktikPie extends ChartWidget
{
    protected ?string $heading = 'Distribusi Status SIP';

    protected ?string $description = 'Komposisi berkas berdasarkan status terkini.';

    protected int|string|array $columnSpan = 1;

    protected ?string $maxHeight = '320px';

    protected function getData(): array
    {
        $statuses = ['masuk', 'proses', 'menunggu_verifikasi_teknis', 'selesai', 'terverifikasi_teknis', 'ditolak_teknis', 'ditolak', 'dibatalkan'];

        $labels = [
            'masuk' => 'Masuk',
            'proses' => 'Proses',
            'menunggu_verifikasi_teknis' => 'Menunggu Verifikasi Teknis',
            'selesai' => 'Selesai',
            'terverifikasi_teknis' => 'Terverifikasi Teknis',
            'ditolak_teknis' => 'Ditolak Teknis',
            'ditolak' => 'Ditolak',
            'dibatalkan' => 'Dibatalkan',
        ];

        $counts = DB::table('surat_izin_praktik')
            ->whereIn('status', $statuses)
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();

        $counts = array_merge(array_fill_keys($statuses, 0), $counts);

        return [
            'labels' => array_map(fn ($s) => $labels[$s], $statuses),
            'datasets' => [
                [
                    'data' => array_values($counts),
                    'backgroundColor' => [
                        '#facc15', // masuk
                        '#3b82f6', // proses
                        '#8b5cf6', // menunggu_verifikasi_teknis
                        '#22c55e', // selesai
                        '#14b8a6', // terverifikasi_teknis
                        '#f97316', // ditolak_teknis
                        '#ef4444', // ditolak
                        '#9ca3af', // dibatalkan
                    ],
                    'borderColor' => 'rgba(255,255,255,0.85)',
                    'borderWidth' => 2,
                    'hoverOffset' => 6,
                ],
            ],
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getOptions(): array
    {
        return [
            'cutout' => '60%',
            'plugins' => [
                'legend' => [
                    'position' => 'right',
                    'labels' => [
                        'usePointStyle' => true,
                        'boxWidth' => 8,
                    ],
                ],
            ],
        ];
    }
}
