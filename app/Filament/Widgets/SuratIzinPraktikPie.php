<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class SuratIzinPraktikPie extends ChartWidget
{
    protected ?string $heading = 'Distribusi Status SIP';

    protected function getData(): array
    {
        $statuses = ['masuk', 'proses', 'selesai', 'ditolak', 'dibatalkan'];

        // Ambil count per status
        $counts = DB::table('surat_izin_praktik')
            ->whereIn('status', $statuses)
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();

        // Pastikan semua status ada, jika belum ada set 0
        $counts = array_merge(array_fill_keys($statuses, 0), $counts);

        return [
            'labels' => array_map(fn($s) => ucfirst($s), $statuses),
            'datasets' => [
                [
                    'data' => array_values($counts),
                    'backgroundColor' => [
                        '#facc15', // masuk - kuning
                        '#3b82f6', // proses - biru
                        '#22c55e', // selesai - hijau
                        '#ef4444', // ditolak - merah
                        '#9ca3af', // dibatalkan - abu
                    ],
                    'hoverOffset' => 4,
                ],
            ],
        ];
    }

    protected function getType(): string
    {
        return 'pie';
    }
}
