<?php

namespace App\Filament\Resources\SuratIzinPraktik\Pages;

use App\Filament\Resources\SuratIzinPraktik\SuratIzinPraktikResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditSuratIzinPraktik extends EditRecord
{
    protected static string $resource = SuratIzinPraktikResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
