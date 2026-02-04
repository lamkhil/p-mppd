<?php

namespace App\Filament\Resources\SuratIzinPraktik\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

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
