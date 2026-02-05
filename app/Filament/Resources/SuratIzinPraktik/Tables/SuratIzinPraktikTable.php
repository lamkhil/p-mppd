<?php

namespace App\Filament\Resources\SuratIzinPraktik\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Tapp\FilamentProgressBarColumn\Tables\Columns\ProgressBarColumn;

class SuratIzinPraktikTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at')
            ->columns([
                TextColumn::make('nomor_register')
                    ->searchable(),
                TextColumn::make('created_at')
                    ->label('Dibuat Tgl')
                    ->dateTime()
                    ->sortable(),
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
                                fn($doc) =>
                                filled($doc['value'] ?? null) ||
                                    filled($doc['file'] ?? null)
                            )
                            ->count();

                        return (int) round(($uploaded / $total) * 100);
                    })
                    ->lowThreshold(50) // < 50% = warning
                    ->dangerColor('rgb(239, 68, 68)')   // merah
                    ->warningColor('rgb(245, 158, 11)') // kuning
                    ->successColor('rgb(34, 197, 94)'), // hijau

                TextColumn::make('nik')
                    ->label('NIK')
                    ->searchable(),
                TextColumn::make('nama')
                    ->searchable(),
                TextColumn::make('email')
                    ->label('Email address')
                    ->searchable(),
                TextColumn::make('nomor_telepon')
                    ->searchable(),
                TextColumn::make('nomor_str')
                    ->label('Nomor STR')
                    ->searchable(),
                TextColumn::make('masa_berlaku_str')
                    ->label('Masa Berlaku STR')
                    ->searchable(),
                TextColumn::make('profesi')
                    ->searchable(),
                TextColumn::make('tempat_praktik')
                    ->searchable(),
                TextColumn::make('nomor_sip')
                    ->label('Nomor SIP')
                    ->searchable(),
                TextColumn::make('tanggal_terbit_sip')
                    ->label('Tanggal Terbit SIP')
                    ->sortable(),
                TextColumn::make('tanggal_akhir_sip')
                    ->label('Tanggal Akhir SIP')
                    ->sortable(),
                TextColumn::make('keterangan')
                    ->wrap()
                    ->searchable(),
                TextColumn::make('updated_at')
                    ->label('Diubah Tgl')
                    ->dateTime()
                    ->sortable()
            ])
            ->filters([
                //
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
