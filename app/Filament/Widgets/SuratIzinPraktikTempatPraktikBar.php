<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class SuratIzinPraktikTempatPraktikBar extends ChartWidget
{
    protected ?string $heading = 'Top 10 Tempat Praktik';

    protected ?string $description = 'Tempat praktik dengan jumlah berkas terbanyak.';

    protected int|string|array $columnSpan = 1;

    protected ?string $maxHeight = '320px';

    protected function getData(): array
    {
        $rows = DB::table('surat_izin_praktik')
            ->select('tempat_praktik', DB::raw('COUNT(*) as total'))
            ->whereNotNull('tempat_praktik')
            ->where('tempat_praktik', '<>', '')
            ->groupBy('tempat_praktik')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        return [
            'labels' => $rows->pluck('tempat_praktik')->toArray(),
            'datasets' => [
                [
                    'label' => 'Jumlah Berkas',
                    'data' => $rows->pluck('total')->toArray(),
                    'backgroundColor' => '#14b8a6',
                    'borderRadius' => 4,
                ],
            ],
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return [
            'indexAxis' => 'y',
            'plugins' => [
                'legend' => ['display' => false],
            ],
            'scales' => [
                'x' => [
                    'beginAtZero' => true,
                    'ticks' => ['precision' => 0],
                ],
            ],
        ];
    }
}
