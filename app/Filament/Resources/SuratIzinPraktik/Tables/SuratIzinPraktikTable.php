<?php

namespace App\Filament\Resources\SuratIzinPraktik\Tables;

use App\Jobs\KirimMppdKeSsw;
use App\Services\Ssw\SswMppdService;
use Carbon\CarbonInterface;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Tapp\FilamentProgressBarColumn\Tables\Columns\ProgressBarColumn;

class SuratIzinPraktikTable
{
    protected const STATUS_LABELS = [
        'masuk' => 'Masuk',
        'proses' => 'Proses',
        'menunggu_verifikasi_teknis' => 'Menunggu Verifikasi Teknis',
        'selesai' => 'Selesai',
        'terverifikasi_teknis' => 'Terverifikasi Teknis',
        'ditolak_teknis' => 'Ditolak Teknis',
        'ditolak' => 'Ditolak',
        'dibatalkan' => 'Dibatalkan',
    ];

    protected const STATUS_COLORS = [
        'masuk' => 'warning',
        'proses' => 'info',
        'menunggu_verifikasi_teknis' => 'primary',
        'selesai' => 'success',
        'terverifikasi_teknis' => 'success',
        'ditolak_teknis' => 'danger',
        'ditolak' => 'danger',
        'dibatalkan' => 'gray',
    ];

    protected const STATUS_ICONS = [
        'masuk' => Heroicon::Inbox,
        'proses' => Heroicon::ArrowPath,
        'menunggu_verifikasi_teknis' => Heroicon::Clock,
        'selesai' => Heroicon::CheckBadge,
        'terverifikasi_teknis' => Heroicon::ShieldCheck,
        'ditolak_teknis' => Heroicon::ShieldExclamation,
        'ditolak' => Heroicon::XCircle,
        'dibatalkan' => Heroicon::NoSymbol,
    ];

    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->striped()
            ->deferLoading()
            ->paginated([10, 25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->columns([
                TextColumn::make('nomor_register')
                    ->label('No. Register')
                    ->searchable()
                    ->copyable()
                    ->copyMessage('Nomor register disalin')
                    ->weight('semibold'),

                TextColumn::make('nama')
                    ->label('Pemohon')
                    ->description(fn ($record) => $record->nik ? 'NIK · '.$record->nik : null)
                    ->searchable(['nama', 'nik'])
                    ->wrap(),

                TextColumn::make('profesi')
                    ->label('Profesi')
                    ->badge()
                    ->color('gray')
                    ->placeholder('—')
                    ->searchable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn ($state) => self::STATUS_LABELS[$state] ?? ucfirst((string) $state))
                    ->color(fn ($state) => self::STATUS_COLORS[$state] ?? 'gray')
                    ->icon(fn ($state) => self::STATUS_ICONS[$state] ?? null)
                    ->sortable(),

                IconColumn::make('ssw_dikirim_pada')
                    ->label('SSW')
                    ->boolean()
                    ->trueIcon(Heroicon::OutlinedCloudArrowUp)
                    ->falseIcon(Heroicon::OutlinedMinusCircle)
                    ->trueColor('success')
                    ->falseColor('gray')
                    ->tooltip(fn ($record) => $record->sudahTerkirimKeSsw()
                        ? 'Terkirim '.$record->ssw_dikirim_pada->translatedFormat('d M Y H:i')
                        : ($record->ssw_error ? 'Gagal: '.$record->ssw_error : 'Belum dikirim ke SSW'))
                    ->visible(fn () => config('ssw.enabled')),

                ProgressBarColumn::make('upload_progress')
                    ->label('Progress Upload')
                    ->maxValue(100)
                    ->getStateUsing(function ($record) {
                        $total = collect($record->kebutuhan_upload ?? [])->count();
                        if ($total === 0) {
                            return 0;
                        }

                        $uploaded = collect($record->document_upload ?? [])
                            ->filter(
                                fn ($doc) => filled($doc['value'] ?? null) ||
                                    filled($doc['file'] ?? null)
                            )
                            ->count();

                        return (int) round(($uploaded / $total) * 100);
                    })
                    ->dangerLabel('Belum Ada Upload')
                    ->warningLabel('Sebagian Terupload')
                    ->successLabel('Selesai Terupload')
                    ->lowThreshold(50)
                    ->dangerColor('rgb(239, 68, 68)')
                    ->warningColor('rgb(245, 158, 11)')
                    ->successColor('rgb(34, 197, 94)'),

                TextColumn::make('created_at')
                    ->label('Diterima')
                    ->dateTime('d M Y H:i')
                    ->tooltip(fn ($record) => optional($record->created_at)->format('d M Y H:i'))
                    ->sortable(),

                TextColumn::make('hari_berjalan')
                    ->label('Hari Berjalan')
                    ->badge()
                    ->color(fn ($record) => self::hariBerjalanColor($record))
                    ->state(function ($record) {
                        if (! $record->created_at) {
                            return '—';
                        }

                        $teks = $record->created_at
                            ->locale('id')
                            ->diffForHumans(now(), [
                                'parts' => 2,
                                'join' => ' ',
                                'syntax' => CarbonInterface::DIFF_ABSOLUTE,
                            ]);

                        return $teks.' berlalu';
                    })
                    ->tooltip(fn ($record) => optional($record->created_at)->format('d M Y H:i'))
                    ->sortable(query: fn (Builder $query, string $direction) => $query->orderBy('created_at', $direction === 'asc' ? 'desc' : 'asc')),

                TextColumn::make('email')
                    ->label('Email')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->searchable(),
                TextColumn::make('nomor_telepon')
                    ->label('Telepon')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->searchable(),
                TextColumn::make('nomor_str')
                    ->label('Nomor STR')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->searchable(),
                TextColumn::make('masa_berlaku_str')
                    ->label('Masa Berlaku STR')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->searchable(),
                TextColumn::make('tempat_praktik')
                    ->label('Tempat Praktik')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->searchable(),
                TextColumn::make('nomor_sip')
                    ->label('Nomor SIP')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->searchable(),
                TextColumn::make('tanggal_terbit_sip')
                    ->label('Terbit SIP')
                    ->date('d M Y')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->sortable(),
                TextColumn::make('tanggal_akhir_sip')
                    ->label('Berakhir SIP')
                    ->date('d M Y')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->sortable(),
                TextColumn::make('keterangan')
                    ->wrap()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->searchable(),
                TextColumn::make('updated_at')
                    ->label('Diubah')
                    ->dateTime('d M Y H:i')
                    ->since()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('proses_teknis')
                    ->label('Status Proses Teknis')
                    ->options([
                        'diproses' => 'Sudah Diproses',
                        'belum' => 'Belum Diproses',
                    ])
                    ->query(function (Builder $query, array $data) {
                        if (! filled($data['value'] ?? null)) {
                            return;
                        }

                        $diproses = ['terverifikasi_teknis', 'ditolak_teknis'];

                        $data['value'] === 'diproses'
                            ? $query->whereIn('status', $diproses)
                            : $query->whereNotIn('status', $diproses);
                    }),
                SelectFilter::make('status')
                    ->label('Status Berkas')
                    ->multiple()
                    ->options(self::STATUS_LABELS),
                SelectFilter::make('profesi')
                    ->label('Profesi')
                    ->options(
                        fn () => \App\Models\SuratIzinPraktik::query()
                            ->whereNotNull('profesi')
                            ->distinct()
                            ->orderBy('profesi')
                            ->pluck('profesi', 'profesi')
                            ->toArray()
                    )
                    ->searchable(),
                TernaryFilter::make('sinkron_ssw')
                    ->label('Sinkronisasi SSW')
                    ->placeholder('Semua')
                    ->trueLabel('Sudah terkirim')
                    ->falseLabel('Belum terkirim')
                    ->visible(fn () => config('ssw.enabled'))
                    ->queries(
                        true: fn (Builder $query) => $query->whereNotNull('ssw_dikirim_pada'),
                        false: fn (Builder $query) => $query->whereNull('ssw_dikirim_pada'),
                        blank: fn (Builder $query) => $query,
                    ),
                Filter::make('akan_expired')
                    ->label('SIP akan kedaluwarsa ≤ '.(int) config('sip.filter_akan_expired_days', 30).' hari')
                    ->toggle()
                    ->query(function (Builder $query) {
                        $days = (int) config('sip.filter_akan_expired_days', 30);

                        $query->whereNotNull('tanggal_akhir_sip')
                            ->whereBetween('tanggal_akhir_sip', [now()->toDateString(), now()->addDays($days)->toDateString()]);
                    }),
            ])
            ->filtersFormColumns(2)
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('tandai_proses')
                        ->label('Tandai Proses')
                        ->icon(Heroicon::ArrowPath)
                        ->color('info')
                        ->requiresConfirmation()
                        ->action(fn (Collection $records) => $records->each->update(['status' => 'proses']))
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('tandai_terverifikasi_teknis')
                        ->label('Tandai Terverifikasi Teknis')
                        ->icon(Heroicon::ShieldCheck)
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading('Tandai Terverifikasi Teknis')
                        ->modalDescription('Status berkas terpilih akan diubah menjadi "Terverifikasi Teknis".')
                        ->action(fn (Collection $records) => $records->each->update(['status' => 'terverifikasi_teknis']))
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('tandai_ditolak_teknis')
                        ->label('Tandai Ditolak Teknis')
                        ->icon(Heroicon::ShieldExclamation)
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading('Tandai Ditolak Teknis')
                        ->modalDescription('Status berkas terpilih akan diubah menjadi "Ditolak Teknis".')
                        ->action(fn (Collection $records) => $records->each->update(['status' => 'ditolak_teknis']))
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('kirim_ssw')
                        ->label('Kirim ke SSW')
                        ->icon(Heroicon::CloudArrowUp)
                        ->color('primary')
                        ->visible(fn () => config('ssw.enabled'))
                        ->requiresConfirmation()
                        ->modalHeading('Kirim ke SSW')
                        ->modalDescription('Pengiriman dijalankan di latar belakang lewat antrean. Berkas yang belum berstatus Proses, sudah pernah terkirim, atau belum punya Jenis Izin SSW akan dilewati.')
                        ->action(function (Collection $records) {
                            $ssw = app(SswMppdService::class);

                            $layak = $records->filter(fn ($record) => $ssw->bolehDikirim($record));

                            if ($layak->isEmpty()) {
                                Notification::make()
                                    ->title('Tidak ada berkas yang bisa dikirim')
                                    ->body('Pastikan status berkas Proses, belum pernah terkirim, dan Jenis Izin SSW sudah diisi.')
                                    ->warning()
                                    ->send();

                                return;
                            }

                            $layak->each(fn ($record) => KirimMppdKeSsw::dispatch($record));

                            $dilewati = $records->count() - $layak->count();

                            Notification::make()
                                ->title($layak->count().' berkas masuk antrean pengiriman')
                                ->body($dilewati > 0
                                    ? $dilewati.' berkas dilewati. Hasil pengiriman tampil di kolom SSW setelah antrean diproses.'
                                    : 'Hasil pengiriman tampil di kolom SSW setelah antrean diproses.')
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('Belum ada berkas')
            ->emptyStateDescription('Berkas yang masuk akan tampil di tabel ini.')
            ->emptyStateIcon(Heroicon::OutlinedInbox);
    }

    public static function hariBerjalanColor($record): string
    {
        if (! $record->created_at) {
            return 'gray';
        }

        $hari = (int) now()->startOfDay()->diffInDays($record->created_at->startOfDay(), true);

        $t = config('sip.hari_berjalan', []);

        return match (true) {
            $hari <= (int) ($t['success'] ?? 1) => 'success',
            $hari <= (int) ($t['info'] ?? 2) => 'info',
            $hari <= (int) ($t['warning'] ?? 3) => 'warning',
            default => 'danger',
        };
    }
}
