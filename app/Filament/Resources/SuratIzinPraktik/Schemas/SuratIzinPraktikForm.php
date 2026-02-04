<?php

namespace App\Filament\Resources\SuratIzinPraktik\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class SuratIzinPraktikForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('nik')
                    ->required(),
                TextInput::make('nama')
                    ->required(),
                Textarea::make('alamat')
                    ->columnSpanFull(),
                TextInput::make('email')
                    ->label('Email address')
                    ->email(),
                TextInput::make('nomor_telepon')
                    ->tel(),
                TextInput::make('nomor_str'),
                TextInput::make('masa_berlaku_str'),
                TextInput::make('nomor_register')
                    ->required(),
                TextInput::make('profesi'),
                TextInput::make('tempat_praktik'),
                Textarea::make('alamat_tempat_praktik')
                    ->columnSpanFull(),
                TextInput::make('nomor_sip'),
                DatePicker::make('tanggal_terbit_sip'),
                DatePicker::make('tanggal_akhir_sip'),
                TextInput::make('keterangan'),
            ]);
    }
}
