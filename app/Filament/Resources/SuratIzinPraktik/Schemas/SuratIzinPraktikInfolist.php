<?php

namespace App\Filament\Resources\SuratIzinPraktik\Schemas;

use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Storage;

class SuratIzinPraktikInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([

                /* ===============================
             * DATA PEMOHON
             * =============================== */
                Section::make('Data Pemohon')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('nik')
                            ->label('NIK'),

                        TextEntry::make('nama')
                            ->label('Nama'),

                        TextEntry::make('email')
                            ->label('Email')
                            ->placeholder('-'),

                        TextEntry::make('nomor_telepon')
                            ->label('Nomor Telepon')
                            ->placeholder('-'),

                        TextEntry::make('alamat')
                            ->label('Alamat')
                            ->placeholder('-')
                            ->columnSpanFull(),
                    ]),

                Section::make('Data Profesi & Praktik')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('profesi')
                            ->label('Profesi')
                            ->placeholder('-'),

                        TextEntry::make('nomor_register')
                            ->label('Nomor Register'),

                        TextEntry::make('tempat_praktik')
                            ->label('Tempat Praktik')
                            ->placeholder('-'),

                        TextEntry::make('nomor_sip')
                            ->label('Nomor SIP')
                            ->placeholder('-'),

                        TextEntry::make('alamat_tempat_praktik')
                            ->label('Alamat Tempat Praktik')
                            ->placeholder('-')
                            ->columnSpanFull(),
                    ]),

                Section::make('STR & SIP')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('nomor_str')
                            ->label('Nomor STR')
                            ->placeholder('-'),

                        TextEntry::make('masa_berlaku_str')
                            ->label('Masa Berlaku STR')
                            ->placeholder('-'),

                        TextEntry::make('tanggal_terbit_sip')
                            ->label('Tanggal Terbit SIP')
                            ->placeholder('-'),

                        TextEntry::make('tanggal_akhir_sip')
                            ->label('Tanggal Akhir SIP')
                            ->placeholder('-')
                            ->columnSpanFull(),
                    ]),

                /* ===============================
             * KETERANGAN & METADATA
             * =============================== */
                Section::make('Keterangan & Riwayat')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('keterangan')
                            ->placeholder('-')
                            ->columnSpanFull(),

                        TextEntry::make('created_at')->dateTime(),
                        TextEntry::make('updated_at')->dateTime(),
                    ]),

                /* ===============================
             * KEBUTUHAN UPLOAD
             * =============================== */
                Section::make('Kebutuhan Upload')
                    ->visible(fn($record) => !empty($record->kebutuhan_upload))
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('link')
                            ->label('Link Form')
                            ->default(function ($record) {
                                return route('sip.upload', [
                                    'record' => $record
                                ]);
                            }),
                        RepeatableEntry::make('kebutuhan_upload')
                            ->columnSpanFull()
                            ->schema([
                                TextEntry::make('name')
                                    ->label('Nama Kebutuhan'),

                                TextEntry::make('type')
                                    ->label('Tipe')
                                    ->formatStateUsing(fn($state) => match ($state) {
                                        'text'  => 'Teks',
                                        'date'  => 'Tanggal',
                                        'pdf'   => 'PDF',
                                        'image' => 'Gambar',
                                        'file'  => 'File Lainnya',
                                        default => $state,
                                    }),

                                TextEntry::make('description')
                                    ->label('Penjelasan')
                                    ->placeholder('-'),
                            ])
                            ->columns(3),
                    ]),

                /* ===============================
             * DOKUMEN TERUNGGAH
             * =============================== */
                Section::make('Dokumen Terunggah')
                    ->visible(fn($record) => !empty($record->document_upload))
                    ->columnSpanFull()
                    ->schema([
                        RepeatableEntry::make('document_upload')
                            ->columnSpanFull()
                            ->schema([
                                TextEntry::make('name')
                                    ->label('Nama Kebutuhan'),

                                TextEntry::make('type')
                                    ->label('Tipe')
                                    ->formatStateUsing(fn($state) => match ($state) {
                                        'text'  => 'Teks',
                                        'date'  => 'Tanggal',
                                        'pdf'   => 'PDF',
                                        'image' => 'Gambar',
                                        'file'  => 'File Lainnya',
                                        default => $state,
                                    }),

                                TextEntry::make('value')
                                    ->label('Isi')
                                    ->visible(
                                        fn($state, $record) =>
                                        in_array($record['type'] ?? null, ['text', 'date'])
                                    )
                                    ->columnSpanFull(),

                                TextEntry::make('file')
                                    ->label('File')
                                    ->url(fn($state) => $state ? Storage::url($state) : null)
                                    ->openUrlInNewTab()
                                    ->color('info')
                                    ->visible(
                                        fn($state, $record) =>
                                        !in_array($record['type'] ?? null, ['text', 'date'])
                                    ),

                                TextEntry::make('uploaded_at')
                                    ->label('Diunggah Pada')
                                    ->dateTime(),
                            ])
                            ->columns(2),
                    ]),
            ]);
    }
}
