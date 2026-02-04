<?php

namespace App\Filament\Resources\SuratIzinPraktik;

use App\Filament\Resources\SuratIzinPraktik\Pages\CreateSuratIzinPraktik;
use App\Filament\Resources\SuratIzinPraktik\Pages\EditSuratIzinPraktik;
use App\Filament\Resources\SuratIzinPraktik\Pages\ListSuratIzinPraktiks;
use App\Filament\Resources\SuratIzinPraktik\Pages\ListSuratIzinPraktiksActivities;
use App\Filament\Resources\SuratIzinPraktik\Pages\ViewSuratIzinPraktik;
use App\Filament\Resources\SuratIzinPraktik\Schemas\SuratIzinPraktikForm;
use App\Filament\Resources\SuratIzinPraktik\Schemas\SuratIzinPraktikInfolist;
use App\Filament\Resources\SuratIzinPraktik\Tables\SuratIzinPraktikTable;
use App\Models\SuratIzinPraktik;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class SuratIzinPraktikResource extends Resource
{
    protected static ?string $model = SuratIzinPraktik::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;
    protected static ?string $slug = 'sip';


    public static function getPluralModelLabel(): string
    {
        return "Surat Izin Praktik";
    }

    protected static ?string $recordTitleAttribute = 'nomor_register';

    public static function form(Schema $schema): Schema
    {
        return SuratIzinPraktikForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return SuratIzinPraktikInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SuratIzinPraktikTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSuratIzinPraktiks::route('/'),
            'create' => CreateSuratIzinPraktik::route('/create'),
            'view' => ViewSuratIzinPraktik::route('/{record}'),
            'edit' => EditSuratIzinPraktik::route('/{record}/edit'),
            'activities' =>  ListSuratIzinPraktiksActivities::route('/{record}/activities')
        ];
    }
}
