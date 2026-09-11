<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class SuratIzinPraktikTrend extends ChartWidget
{
    protected ?string $heading = 'Tren Permohonan SIP per Bulan';

    protected ?string $description = 'Jumlah berkas yang masuk per status sepanjang tahun berjalan.';

    protected int|string|array $columnSpan = 2;

    protected ?string $maxHeight = '320px';

    public ?string $filter = 'tahun_ini';

    protected function getFilters(): ?array
    {
        return [
            'tahun_ini' => 'Tahun '.now()->year,
            'tahun_lalu' => 'Tahun '.now()->subYear()->year,
        ];
    }

    protected function getData(): array
    {
        $year = $this->filter === 'tahun_lalu' ? now()->subYear()->year : now()->year;

        $months = range(1, 12);
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

        $colors = [
            'masuk' => '#facc15',
            'proses' => '#3b82f6',
            'menunggu_verifikasi_teknis' => '#8b5cf6',
            'selesai' => '#22c55e',
            'terverifikasi_teknis' => '#14b8a6',
            'ditolak_teknis' => '#f97316',
            'ditolak' => '#ef4444',
            'dibatalkan' => '#9ca3af',
        ];

        // Ambil semua data sekali query, kelompokkan di PHP
        $rows = DB::table('surat_izin_praktik')
            ->selectRaw('status, EXTRACT(MONTH FROM created_at)::int AS month, COUNT(*) AS total')
            ->whereRaw('EXTRACT(YEAR FROM created_at) = ?', [$year])
            ->whereIn('status', $statuses)
            ->groupBy('status', 'month')
            ->get();

        $matrix = [];
        foreach ($statuses as $s) {
            $matrix[$s] = array_fill(1, 12, 0);
        }
        foreach ($rows as $r) {
            $matrix[$r->status][(int) $r->month] = (int) $r->total;
        }

        $datasets = [];
        foreach ($statuses as $status) {
            $datasets[] = [
                'label' => $labels[$status],
                'data' => array_values($matrix[$status]),
                'borderColor' => $colors[$status],
                'backgroundColor' => $colors[$status].'33',
                'tension' => 0.3,
                'fill' => false,
                'borderWidth' => 2,
                'pointRadius' => 3,
                'pointHoverRadius' => 5,
            ];
        }

        return [
            'labels' => collect($months)->map(fn ($m) => date('M', mktime(0, 0, 0, $m, 1)))->toArray(),
            'datasets' => $datasets,
        ];
    }

    public function getType(): string
    {
        return 'line';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'labels' => [
                        'usePointStyle' => true,
                        'boxWidth' => 8,
                    ],
                ],
            ],
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'ticks' => ['precision' => 0],
                ],
            ],
        ];
    }
}
