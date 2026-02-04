<?php

namespace App\Filament\Imports;

use App\Models\SuratIzinPraktik;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Support\Number;

class SuratIzinPraktikImporter extends Importer
{
    protected static ?string $model = SuratIzinPraktik::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('nik')
                ->requiredMapping()
                ->rules(['required', 'max:20']),
            ImportColumn::make('nama')
                ->requiredMapping()
                ->rules(['required', 'max:255']),
            ImportColumn::make('alamat'),
            ImportColumn::make('email')
                ->rules(['email', 'max:255']),
            ImportColumn::make('nomor_telepon')
                ->rules(['max:20']),
            ImportColumn::make('nomor_str')
                ->rules(['max:255']),
            ImportColumn::make('masa_berlaku_str')
                ->rules(['max:255']),
            ImportColumn::make('nomor_register')
                ->requiredMapping()
                ->rules(['required', 'max:255']),
            ImportColumn::make('profesi')
                ->rules(['max:255']),
            ImportColumn::make('tempat_praktik')
                ->rules(['max:255']),
            ImportColumn::make('alamat_tempat_praktik'),
            ImportColumn::make('nomor_sip')
                ->rules(['max:255']),
            ImportColumn::make('tanggal_terbit_sip'),
            ImportColumn::make('tanggal_akhir_sip'),
            ImportColumn::make('keterangan')
                ->rules(['max:255']),
        ];
    }

    public function resolveRecord(): SuratIzinPraktik
    {
        return SuratIzinPraktik::firstOrNew([
            'nomor_register' => $this->data['nomor_register'],
        ]);
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Your surat izin praktik import has completed and ' . Number::format($import->successful_rows) . ' ' . str('row')->plural($import->successful_rows) . ' imported.';

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' ' . Number::format($failedRowsCount) . ' ' . str('row')->plural($failedRowsCount) . ' failed to import.';
        }

        return $body;
    }
}
