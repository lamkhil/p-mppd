<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class StatOverViewSuratIzinPraktik extends StatsOverviewWidget
{
    protected ?string $heading = 'Surat Izin Praktik';

    protected function getStats(): array
    {
        // Hitung jumlah per status
        $statuses = ['masuk', 'proses', 'selesai', 'ditolak', 'dibatalkan'];
        $counts = DB::table('surat_izin_praktik')
            ->whereIn('status', $statuses)
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();

        // Pastikan semua status ada, jika belum ada set 0
        $counts = array_merge(array_fill_keys($statuses, 0), $counts);

        return [
            Stat::make('Masuk', $counts['masuk'])->color('warning'),
            Stat::make('Proses', $counts['proses'])->color('info'),
            Stat::make('Selesai', $counts['selesai'])->color('success'),
            Stat::make('Ditolak', $counts['ditolak'])->color('danger'),
            Stat::make('Dibatalkan', $counts['dibatalkan'])->color('danger')
                ->columnSpan(2),
        ];
    }
}
