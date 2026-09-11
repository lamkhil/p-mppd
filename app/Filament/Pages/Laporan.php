<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\BerkasTerbaruTable;
use App\Filament\Widgets\SipKedaluwarsaTable;
use App\Filament\Widgets\StatOverViewSuratIzinPraktik;
use App\Filament\Widgets\SuratIzinPraktikPie;
use App\Filament\Widgets\SuratIzinPraktikProfesiBar;
use App\Filament\Widgets\SuratIzinPraktikTempatPraktikBar;
use App\Filament\Widgets\SuratIzinPraktikTrend;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class Laporan extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static string|BackedEnum|null $activeNavigationIcon = Heroicon::ChartBar;

    protected static string|UnitEnum|null $navigationGroup = 'Laporan';

    protected static ?string $navigationLabel = 'Laporan & Analitik';

    protected static ?string $title = 'Laporan & Analitik';

    protected static ?int $navigationSort = 10;

    protected string $view = 'filament.pages.laporan';

    public function getWidgets(): array
    {
        return [
            StatOverViewSuratIzinPraktik::class,
            SuratIzinPraktikTrend::class,
            SuratIzinPraktikPie::class,
            SuratIzinPraktikProfesiBar::class,
            SuratIzinPraktikTempatPraktikBar::class,
            SipKedaluwarsaTable::class,
            BerkasTerbaruTable::class,
        ];
    }

    public function getColumns(): int|string|array
    {
        return [
            'md' => 2,
            'xl' => 3,
        ];
    }
}
