<?php

namespace App\Filament\Widgets;

use App\Models\SuratIzinPraktik;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class SipKedaluwarsaTable extends BaseWidget
{
    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        $window = (int) config('sip.kedaluwarsa_window_days', 60);

        return $table
            ->heading("SIP Akan Kedaluwarsa (≤ {$window} hari)")
            ->description("Daftar SIP yang akan habis masa berlakunya dalam {$window} hari ke depan.")
            ->query(
                SuratIzinPraktik::query()
                    ->whereNotNull('tanggal_akhir_sip')
                    ->whereBetween('tanggal_akhir_sip', [
                        now()->toDateString(),
                        now()->addDays($window)->toDateString(),
                    ])
                    ->orderBy('tanggal_akhir_sip')
            )
            ->columns([
                TextColumn::make('nomor_register')
                    ->label('No. Register')
                    ->weight('semibold'),
                TextColumn::make('nama')
                    ->label('Pemohon')
                    ->wrap(),
                TextColumn::make('nomor_sip')
                    ->label('Nomor SIP')
                    ->placeholder('—'),
                TextColumn::make('tanggal_akhir_sip')
                    ->label('Berakhir')
                    ->date('d M Y'),
                TextColumn::make('tanggal_akhir_sip')
                    ->label('Sisa Hari')
                    ->state(fn ($record) => $record->tanggal_akhir_sip
                        ? (int) now()->startOfDay()->diffInDays(\Carbon\Carbon::parse($record->tanggal_akhir_sip)->startOfDay(), false)
                        : null)
                    ->badge()
                    ->color(fn ($state) => $state === null
                        ? 'gray'
                        : ($state <= 7 ? 'danger' : ($state <= 30 ? 'warning' : 'info')))
                    ->formatStateUsing(fn ($state) => $state === null ? '—' : $state.' hari'),
            ])
            ->emptyStateHeading('Tidak ada SIP kedaluwarsa')
            ->emptyStateDescription("Tidak ada SIP yang akan kedaluwarsa dalam {$window} hari ke depan.")
            ->emptyStateIcon(Heroicon::OutlinedShieldCheck)
            ->paginated([5, 10, 25]);
    }
}
