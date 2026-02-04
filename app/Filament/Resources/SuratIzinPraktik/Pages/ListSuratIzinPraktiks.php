<?php

namespace App\Filament\Resources\SuratIzinPraktik\Pages;

use App\Filament\Imports\SuratIzinPraktikImporter;
use App\Filament\Resources\SuratIzinPraktik\SuratIzinPraktikResource;
use Filament\Actions\ImportAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Filament\Schemas\Components\Tabs\Tab;

class ListSuratIzinPraktiks extends ListRecords
{
    protected static string $resource = SuratIzinPraktikResource::class;

    public int $countMasuk = 0;
    public int $countProses = 0;
    public int $countSelesai = 0;
    public int $countDitolak = 0;
    public int $countDibatalkan = 0;

    public function mount(): void
    {

        $model = static::getResource()::getModel();

        $counts = $model::selectRaw("
                status,
                COUNT(*) as total
            ")
            ->whereIn('status', ['masuk', 'proses', 'selesai', 'ditolak', 'dibatalkan'])
            ->groupBy('status')
            ->pluck('total', 'status');

        $this->countMasuk   = $counts['masuk']   ?? 0;
        $this->countProses  = $counts['proses']  ?? 0;
        $this->countSelesai = $counts['selesai'] ?? 0;
        $this->countDitolak = $counts['ditolak'] ?? 0;
        $this->countDibatalkan = $counts['dibatalkan'] ?? 0;
        parent::mount();
    }

    protected function getHeaderActions(): array
    {
        return [
            ImportAction::make()
                ->label('Import .CSV')
                ->importer(SuratIzinPraktikImporter::class),
        ];
    }

    public function getTabs(): array
    {
        return [
            'masuk' => Tab::make('Masuk')
                ->badge($this->countMasuk)
                ->badgeColor('warning')
                ->modifyQueryUsing(
                    fn(Builder $query) =>
                    $query->where('status', 'masuk')
                ),

            'proses' => Tab::make('Proses')
                ->badge($this->countProses)
                ->badgeColor('info')
                ->modifyQueryUsing(
                    fn(Builder $query) =>
                    $query->where('status', 'proses')
                ),

            'selesai' => Tab::make('Selesai')
                ->badge($this->countSelesai)
                ->badgeColor('success')
                ->modifyQueryUsing(
                    fn(Builder $query) =>
                    $query->where('status', 'selesai')
                ),

            'ditolak' => Tab::make('Ditolak')
                ->badge($this->countDitolak)
                ->badgeColor('danger')
                ->modifyQueryUsing(
                    fn(Builder $query) =>
                    $query->where('status', 'ditolak')
                ),
            'dibatalkan' => Tab::make('Dibatalkan')
                ->badge($this->countSelesai)
                ->badgeColor('danger')
                ->modifyQueryUsing(
                    fn(Builder $query) =>
                    $query->where('status', 'dibatalkan')
                ),
        ];
    }

    protected function getDefaultTab(): ?string
    {
        return 'masuk';
    }
}
