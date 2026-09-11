<?php

namespace App\Filament\Resources\SuratIzinPraktik\Pages;

use App\Filament\Imports\SuratIzinPraktikImporter;
use App\Filament\Resources\SuratIzinPraktik\SuratIzinPraktikResource;
use Filament\Actions\ImportAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

class ListSuratIzinPraktiks extends ListRecords
{
    protected static string $resource = SuratIzinPraktikResource::class;

    public int $countSemua = 0;

    public int $countMasuk = 0;

    public int $countProses = 0;

    public int $countMenungguVerifikasiTeknis = 0;

    public int $countSelesai = 0;

    public int $countTerverifikasiTeknis = 0;

    public int $countDitolakTeknis = 0;

    public int $countDitolak = 0;

    public int $countDibatalkan = 0;

    public function mount(): void
    {
        parent::mount();
    }

    public function getCounts()
    {
        $model = static::getResource()::getModel();

        $counts = $model::selectRaw('
                status,
                COUNT(*) as total
            ')
            ->whereIn('status', ['masuk', 'proses', 'menunggu_verifikasi_teknis', 'selesai', 'terverifikasi_teknis', 'ditolak_teknis', 'ditolak', 'dibatalkan'])
            ->groupBy('status')
            ->pluck('total', 'status');

        $this->countMasuk = $counts['masuk'] ?? 0;
        $this->countProses = $counts['proses'] ?? 0;
        $this->countMenungguVerifikasiTeknis = $counts['menunggu_verifikasi_teknis'] ?? 0;
        $this->countSelesai = $counts['selesai'] ?? 0;
        $this->countTerverifikasiTeknis = $counts['terverifikasi_teknis'] ?? 0;
        $this->countDitolakTeknis = $counts['ditolak_teknis'] ?? 0;
        $this->countDitolak = $counts['ditolak'] ?? 0;
        $this->countDibatalkan = $counts['dibatalkan'] ?? 0;
        $this->countSemua = $counts->sum();
    }

    protected function getHeaderActions(): array
    {
        return [
            ImportAction::make()
                ->label('Import CSV')
                ->icon(Heroicon::ArrowUpTray)
                ->importer(SuratIzinPraktikImporter::class),
        ];
    }

    public function getTabs(): array
    {
        $this->getCounts();

        return [
            'all' => Tab::make('Semua')
                ->icon(Heroicon::QueueList)
                ->badge($this->countSemua),

            'masuk' => Tab::make('Masuk')
                ->icon(Heroicon::Inbox)
                ->badge($this->countMasuk)
                ->badgeColor('warning')
                ->modifyQueryUsing(
                    fn (Builder $query) => $query->where('status', 'masuk')
                ),

            'proses' => Tab::make('Proses')
                ->icon(Heroicon::ArrowPath)
                ->badge($this->countProses)
                ->badgeColor('info')
                ->modifyQueryUsing(
                    fn (Builder $query) => $query->where('status', 'proses')
                ),

            'menunggu_verifikasi_teknis' => Tab::make('Menunggu Verifikasi Teknis')
                ->icon(Heroicon::Clock)
                ->badge($this->countMenungguVerifikasiTeknis)
                ->badgeColor('primary')
                ->modifyQueryUsing(
                    fn (Builder $query) => $query->where('status', 'menunggu_verifikasi_teknis')
                ),

            'selesai' => Tab::make('Selesai')
                ->icon(Heroicon::CheckBadge)
                ->badge($this->countSelesai)
                ->badgeColor('success')
                ->modifyQueryUsing(
                    fn (Builder $query) => $query->where('status', 'selesai')
                ),

            'terverifikasi_teknis' => Tab::make('Terverifikasi Teknis')
                ->icon(Heroicon::ShieldCheck)
                ->badge($this->countTerverifikasiTeknis)
                ->badgeColor('success')
                ->modifyQueryUsing(
                    fn (Builder $query) => $query->where('status', 'terverifikasi_teknis')
                ),

            'ditolak_teknis' => Tab::make('Ditolak Teknis')
                ->icon(Heroicon::ShieldExclamation)
                ->badge($this->countDitolakTeknis)
                ->badgeColor('danger')
                ->modifyQueryUsing(
                    fn (Builder $query) => $query->where('status', 'ditolak_teknis')
                ),

            'ditolak' => Tab::make('Ditolak')
                ->icon(Heroicon::XCircle)
                ->badge($this->countDitolak)
                ->badgeColor('danger')
                ->modifyQueryUsing(
                    fn (Builder $query) => $query->where('status', 'ditolak')
                ),

            'dibatalkan' => Tab::make('Dibatalkan')
                ->icon(Heroicon::NoSymbol)
                ->badge($this->countDibatalkan)
                ->badgeColor('gray')
                ->modifyQueryUsing(
                    fn (Builder $query) => $query->where('status', 'dibatalkan')
                ),
        ];
    }

    protected function getDefaultTab(): ?string
    {
        return 'all';
    }
}
