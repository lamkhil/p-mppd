<?php

namespace App\Filament\Resources\SuratIzinPraktik\Schemas;

use App\Services\Ssw\SswMppdService;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class SuratIzinPraktikForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Data Pemohon')
                    ->description('Identitas pemohon sesuai dokumen resmi.')
                    ->icon(Heroicon::OutlinedIdentification)
                    ->columns(2)
                    ->schema([
                        TextInput::make('nik')
                            ->label('NIK')
                            ->required()
                            ->maxLength(20),
                        TextInput::make('nama')
                            ->label('Nama Lengkap')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('email')
                            ->label('Email')
                            ->email()
                            ->maxLength(255),
                        TextInput::make('nomor_telepon')
                            ->label('Nomor Telepon')
                            ->tel()
                            ->maxLength(20),
                        Textarea::make('alamat')
                            ->label('Alamat Domisili')
                            ->rows(2)
                            ->columnSpanFull(),
                    ]),

                Section::make('Registrasi & Profesi')
                    ->description('Identitas registrasi dan profesi yang dimohonkan.')
                    ->icon(Heroicon::OutlinedBriefcase)
                    ->columns(2)
                    ->schema([
                        TextInput::make('nomor_register')
                            ->label('Nomor Register')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),
                        TextInput::make('profesi')
                            ->label('Profesi')
                            ->maxLength(255),
                        TextInput::make('tempat_praktik')
                            ->label('Tempat Praktik')
                            ->maxLength(255),
                        Select::make('status')
                            ->label('Status Berkas')
                            ->options([
                                'masuk' => 'Masuk',
                                'proses' => 'Proses',
                                'menunggu_verifikasi_teknis' => 'Menunggu Verifikasi Teknis',
                                'selesai' => 'Selesai',
                                'terverifikasi_teknis' => 'Terverifikasi Teknis',
                                'ditolak_teknis' => 'Ditolak Teknis',
                                'ditolak' => 'Ditolak',
                                'dibatalkan' => 'Dibatalkan',
                            ])
                            ->default('masuk')
                            ->native(false),
                        Textarea::make('alamat_tempat_praktik')
                            ->label('Alamat Tempat Praktik')
                            ->rows(2)
                            ->columnSpanFull(),
                    ]),

                Section::make('Integrasi SSW')
                    ->description('Menentukan jenis izin yang dipakai saat permohonan dikirim ke SSW.')
                    ->icon(Heroicon::OutlinedCloudArrowUp)
                    ->columns(2)
                    ->visible(fn () => config('ssw.enabled'))
                    ->schema([
                        Select::make('id_ijin')
                            ->label('Jenis Izin SSW')
                            ->helperText('Diambil dari master izin SSW (layanan MPPD). Kosongkan untuk memakai default di konfigurasi.')
                            ->native(false)
                            ->searchable()
                            ->preload()
                            // Opsi diambil dari cache master izin; kalau SSW tidak
                            // bisa dihubungi, form tetap terbuka dengan opsi kosong.
                            ->options(fn () => app(SswMppdService::class)->opsiIzinAman())
                            ->default(config('ssw.defaults.id_ijin')),

                        Select::make('jenis_permohonan')
                            ->label('Jenis Permohonan')
                            ->native(false)
                            ->options(config('ssw.jenis_permohonan_options'))
                            ->default(config('ssw.defaults.jenis_permohonan')),
                    ]),

                Section::make('STR & SIP')
                    ->description('Detail dokumen STR dan SIP.')
                    ->icon(Heroicon::OutlinedShieldCheck)
                    ->columns(3)
                    ->schema([
                        TextInput::make('nomor_str')
                            ->label('Nomor STR')
                            ->maxLength(255),
                        TextInput::make('masa_berlaku_str')
                            ->label('Masa Berlaku STR')
                            ->maxLength(255),
                        TextInput::make('nomor_sip')
                            ->label('Nomor SIP')
                            ->maxLength(255),
                        DatePicker::make('tanggal_terbit_sip')
                            ->label('Tanggal Terbit SIP')
                            ->native(false)
                            ->displayFormat('d M Y'),
                        DatePicker::make('tanggal_akhir_sip')
                            ->label('Tanggal Akhir SIP')
                            ->native(false)
                            ->displayFormat('d M Y')
                            ->afterOrEqual('tanggal_terbit_sip'),
                        TextInput::make('keterangan')
                            ->label('Keterangan')
                            ->maxLength(255)
                            ->columnSpan(3),
                    ]),
            ]);
    }
}
