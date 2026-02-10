<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class SuratIzinPraktikTrend extends ChartWidget
{
    protected ?string $heading = 'Tren SIP per Bulan';

    protected function getData(): array
    {
        $months = range(1, 12);
        $statuses = ['masuk', 'proses', 'selesai', 'ditolak', 'dibatalkan'];

        $datasets = [];

        foreach ($statuses as $status) {
            $datasets[] = [
                'label' => ucfirst($status),
                'data' => collect($months)->map(
                    fn($m) =>
                    DB::table('surat_izin_praktik')
                        ->whereYear('created_at', now()->year)
                        ->whereMonth('created_at', $m)
                        ->where('status', $status)
                        ->count()
                )->toArray(),
                'borderColor' => match ($status) {
                    'masuk' => '#facc15',       // kuning
                    'proses' => '#3b82f6',      // biru
                    'selesai' => '#22c55e',     // hijau
                    'ditolak' => '#ef4444',     // merah
                    'dibatalkan' => '#9ca3af',  // abu-abu
                },
                'fill' => false,
            ];
        }

        return [
            'labels' => collect($months)->map(fn($m) => date('M', mktime(0, 0, 0, $m, 1)))->toArray(),
            'datasets' => $datasets,
        ];
    }

    public function getType(): string
    {
        return 'line';
    }
}
