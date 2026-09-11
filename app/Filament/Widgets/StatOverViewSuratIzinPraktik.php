<?php

namespace App\Filament\Widgets;

use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class StatOverViewSuratIzinPraktik extends StatsOverviewWidget
{
    protected ?string $heading = 'Ringkasan Surat Izin Praktik';

    protected ?string $description = 'Distribusi berkas per status berkas yang dimohonkan.';

    protected int|string|array $columnSpan = 'full';

    protected function getColumns(): int
    {
        return 4;
    }

    protected function getStats(): array
    {
        $statuses = ['masuk', 'proses', 'menunggu_verifikasi_teknis', 'selesai', 'terverifikasi_teknis', 'ditolak_teknis', 'ditolak', 'dibatalkan'];

        $counts = DB::table('surat_izin_praktik')
            ->whereIn('status', $statuses)
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();

        $counts = array_merge(array_fill_keys($statuses, 0), $counts);
        $total = array_sum($counts);
        $pct = fn (int $n) => $total > 0 ? round($n / $total * 100).'%' : '0%';

        return [
            Stat::make('Total Berkas', $total)
                ->description('Seluruh berkas SIP terdaftar')
                ->descriptionIcon(Heroicon::OutlinedDocumentDuplicate)
                ->color('primary'),

            Stat::make('Masuk', $counts['masuk'])
                ->description($pct($counts['masuk']).' dari total')
                ->descriptionIcon(Heroicon::OutlinedInbox)
                ->color('warning'),

            Stat::make('Proses', $counts['proses'])
                ->description($pct($counts['proses']).' dari total')
                ->descriptionIcon(Heroicon::OutlinedArrowPath)
                ->color('info'),

            Stat::make('Menunggu Verifikasi Teknis', $counts['menunggu_verifikasi_teknis'])
                ->description($pct($counts['menunggu_verifikasi_teknis']).' dari total')
                ->descriptionIcon(Heroicon::OutlinedClock)
                ->color('primary'),

            Stat::make('Selesai', $counts['selesai'])
                ->description($pct($counts['selesai']).' dari total')
                ->descriptionIcon(Heroicon::OutlinedCheckBadge)
                ->color('success'),

            Stat::make('Terverifikasi Teknis', $counts['terverifikasi_teknis'])
                ->description($pct($counts['terverifikasi_teknis']).' dari total')
                ->descriptionIcon(Heroicon::OutlinedShieldCheck)
                ->color('success'),

            Stat::make('Ditolak Teknis', $counts['ditolak_teknis'])
                ->description($pct($counts['ditolak_teknis']).' dari total')
                ->descriptionIcon(Heroicon::OutlinedShieldExclamation)
                ->color('danger'),

            Stat::make('Ditolak', $counts['ditolak'])
                ->description($pct($counts['ditolak']).' dari total')
                ->descriptionIcon(Heroicon::OutlinedXCircle)
                ->color('danger'),

            Stat::make('Dibatalkan', $counts['dibatalkan'])
                ->description($pct($counts['dibatalkan']).' dari total')
                ->descriptionIcon(Heroicon::OutlinedNoSymbol)
                ->color('gray'),
        ];
    }
}
