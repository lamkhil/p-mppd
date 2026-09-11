<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class SuratIzinPraktikProfesiBar extends ChartWidget
{
    protected ?string $heading = 'Permohonan per Profesi';

    protected ?string $description = '10 profesi dengan jumlah berkas terbanyak.';

    protected int|string|array $columnSpan = 1;

    protected ?string $maxHeight = '320px';

    protected function getData(): array
    {
        $rows = DB::table('surat_izin_praktik')
            ->select('profesi', DB::raw('COUNT(*) as total'))
            ->whereNotNull('profesi')
            ->where('profesi', '<>', '')
            ->groupBy('profesi')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        return [
            'labels' => $rows->pluck('profesi')->toArray(),
            'datasets' => [
                [
                    'label' => 'Jumlah Berkas',
                    'data' => $rows->pluck('total')->toArray(),
                    'backgroundColor' => '#3b82f6',
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
