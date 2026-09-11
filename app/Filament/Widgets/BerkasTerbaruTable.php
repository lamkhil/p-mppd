<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\SuratIzinPraktik\Tables\SuratIzinPraktikTable;
use App\Models\SuratIzinPraktik;
use Carbon\CarbonInterface;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class BerkasTerbaruTable extends BaseWidget
{
    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Berkas Terbaru')
            ->description('10 berkas terakhir yang diterima sistem.')
            ->query(
                SuratIzinPraktik::query()
                    ->latest('created_at')
                    ->limit(10)
            )
            ->columns([
                TextColumn::make('nomor_register')
                    ->label('No. Register')
                    ->weight('semibold')
                    ->searchable(),
                TextColumn::make('nama')
                    ->label('Pemohon')
                    ->description(fn ($record) => $record->nik ? 'NIK · '.$record->nik : null)
                    ->wrap(),
                TextColumn::make('profesi')
                    ->label('Profesi')
                    ->badge()
                    ->color('gray')
                    ->placeholder('—'),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'masuk' => 'Masuk',
                        'proses' => 'Proses',
                        'menunggu_verifikasi_teknis' => 'Menunggu Verifikasi Teknis',
                        'selesai' => 'Selesai',
                        'terverifikasi_teknis' => 'Terverifikasi Teknis',
                        'ditolak_teknis' => 'Ditolak Teknis',
                        'ditolak' => 'Ditolak',
                        'dibatalkan' => 'Dibatalkan',
                        default => ucfirst((string) $state),
                    })
                    ->color(fn ($state) => match ($state) {
                        'masuk' => 'warning',
                        'proses' => 'info',
                        'menunggu_verifikasi_teknis' => 'primary',
                        'selesai',
                        'terverifikasi_teknis' => 'success',
                        'ditolak_teknis',
                        'ditolak' => 'danger',
                        default => 'gray',
                    })
                    ->icon(fn ($state) => match ($state) {
                        'masuk' => Heroicon::Inbox,
                        'proses' => Heroicon::ArrowPath,
                        'menunggu_verifikasi_teknis' => Heroicon::Clock,
                        'selesai' => Heroicon::CheckBadge,
                        'terverifikasi_teknis' => Heroicon::ShieldCheck,
                        'ditolak_teknis' => Heroicon::ShieldExclamation,
                        'ditolak' => Heroicon::XCircle,
                        'dibatalkan' => Heroicon::NoSymbol,
                        default => null,
                    }),
                TextColumn::make('created_at')
                    ->label('Diterima')
                    ->dateTime('d M Y H:i')
                    ->since(),
                TextColumn::make('hari_berjalan')
                    ->label('Hari Berjalan')
                    ->badge()
                    ->color(fn ($record) => SuratIzinPraktikTable::hariBerjalanColor($record))
                    ->state(function ($record) {
                        if (! $record->created_at) {
                            return '—';
                        }

                        return $record->created_at
                            ->locale('id')
                            ->diffForHumans(now(), [
                                'parts' => 2,
                                'join' => ' ',
                                'syntax' => CarbonInterface::DIFF_ABSOLUTE,
                            ]).' berlalu';
                    })
                    ->tooltip(fn ($record) => optional($record->created_at)->format('d M Y H:i')),
            ])
            ->paginated(false);
    }
}
