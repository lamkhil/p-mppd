<?php

namespace App\Filament\Resources\SuratIzinPraktik\Pages;

use App\Filament\Resources\SuratIzinPraktik\SuratIzinPraktikResource;
use App\Models\SuratIzinPraktik;
use App\Services\Ssw\Exceptions\SswException;
use App\Services\Ssw\Exceptions\SswRequestException;
use App\Services\Ssw\SswMppdService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Colors\Color;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;

class ViewSuratIzinPraktik extends ViewRecord
{
    protected static string $resource = SuratIzinPraktikResource::class;

    protected function getHeaderActions(): array
    {
        return [

            /* ===============================
             * PROSES PERMOHONAN
             * =============================== */
            Action::make('proses')
                ->label('Proses Permohonan')
                ->icon(Heroicon::PaperAirplane)
                ->color('info')
                ->visible(fn () => $this->record->status === 'masuk')
                ->schema([
                    Checkbox::make('need_update')
                        ->label('Perlu melengkapi berkas?')
                        ->live(),

                    Repeater::make('kebutuhan_upload')
                        ->visible(fn ($get) => $get('need_update') === true)
                        ->columns(2)
                        ->schema([
                            TextInput::make('name')
                                ->label('Nama Kebutuhan')
                                ->required(),

                            Select::make('type')
                                ->label('Tipe')
                                ->native(false)
                                ->required()
                                ->options([
                                    'text' => 'Teks',
                                    'date' => 'Tanggal',
                                    'pdf' => 'PDF',
                                    'image' => 'Gambar',
                                    'file' => 'File Lainnya',
                                ]),

                            TextInput::make('description')
                                ->label('Penjelasan')
                                ->columnSpanFull(),
                        ]),

                    Textarea::make('catatan')
                        ->label('Catatan Petugas'),
                ])
                ->action(
                    fn (array $data) => $this->record->update([
                        'status' => 'proses',
                        'kebutuhan_upload' => $data['need_update']
                            ? $data['kebutuhan_upload']
                            : null,
                        'keterangan' => $data['catatan'] ?? $this->record->catatan,
                    ])
                ),

            /* ===============================
             * AMBIL DATA LAMA
             * =============================== */
            Action::make('ambilDataLama')
                ->label('Ambil Data Lama')
                ->icon(Heroicon::DocumentDuplicate)
                ->color('gray')
                ->visible(
                    fn () => in_array($this->record->status, ['masuk', 'proses'], true)
                        && $this->riwayatPengajuan()->isNotEmpty()
                )
                ->schema([
                    Select::make('sumber')
                        ->label('Ambil dari pengajuan')
                        ->helperText('Hanya dokumen yang namanya cocok dengan kebutuhan pengajuan ini yang akan disalin, dan hanya mengisi yang masih kosong.')
                        ->native(false)
                        ->required()
                        ->options(
                            fn () => $this->riwayatPengajuan()
                                ->mapWithKeys(fn ($r) => [
                                    $r->getKey() => $r->nomor_register
                                        .' — '.$r->created_at?->format('d/m/Y')
                                        .' ('.count($r->document_upload ?? []).' dokumen)',
                                ])
                                ->all()
                        ),
                ])
                ->action(function (array $data) {
                    $sumber = SuratIzinPraktik::find($data['sumber']);

                    if (! $sumber) {
                        Notification::make()
                            ->title('Pengajuan sumber tidak ditemukan')
                            ->danger()
                            ->send();

                        return;
                    }

                    $kebutuhanNames = collect($this->record->kebutuhan_upload ?? [])
                        ->pluck('name')
                        ->filter()
                        ->all();

                    $existing = collect($this->record->document_upload ?? []);
                    $existingNames = $existing->pluck('name')->filter()->all();

                    // Dokumen lama: cocok name dengan kebutuhan, dan belum ada
                    // di pengajuan ini (isi yang kosong saja).
                    $tambahan = collect($sumber->document_upload ?? [])
                        ->filter(
                            fn ($doc) => isset($doc['name'])
                                && in_array($doc['name'], $kebutuhanNames, true)
                                && ! in_array($doc['name'], $existingNames, true)
                        )
                        ->unique('name')
                        ->map(fn ($doc) => [
                            'name' => $doc['name'],
                            'type' => $doc['type'] ?? null,
                            'value' => $doc['value'] ?? null,
                            'file' => $doc['file'] ?? null,
                            'uploaded_at' => $doc['uploaded_at'] ?? now(),
                        ])
                        ->values();

                    if ($tambahan->isEmpty()) {
                        Notification::make()
                            ->title('Tidak ada dokumen yang bisa diambil')
                            ->body('Semua kebutuhan yang cocok sudah terisi pada pengajuan ini.')
                            ->warning()
                            ->send();

                        return;
                    }

                    $this->record->update([
                        'document_upload' => $existing->concat($tambahan)->values()->toArray(),
                    ]);

                    Notification::make()
                        ->title('Berhasil mengambil data lama')
                        ->body($tambahan->count().' dokumen disalin dari pengajuan '.$sumber->nomor_register.'.')
                        ->success()
                        ->send();
                }),

            /* ===============================
             * FOLLOW UP PEMOHON
             * =============================== */
            Action::make('followUp')
                ->label('Follow Up Pemohon')
                ->icon(Heroicon::ChatBubbleLeftRight)
                ->color('warning')
                ->visible(
                    fn () => $this->record->status === 'proses'
                        && ! empty($this->record->kebutuhan_upload)
                )
                ->schema([
                    Radio::make('channel')
                        ->label('Media Follow Up')
                        ->required()
                        ->options([
                            'whatsapp' => 'WhatsApp',
                            'email' => 'Email',
                        ]),

                    Textarea::make('pesan')
                        ->label('Pesan')
                        ->rows(6)
                        ->default(function () {
                            $record = $this->record;

                            $link = route('sip.upload', [
                                'record' => $record,
                            ]);

                            $kebutuhan = collect($record->kebutuhan_upload ?? [])
                                ->pluck('name')
                                ->map(fn ($item, $i) => ($i + 1).'. '.$item)
                                ->implode("\n");

                            return implode("\n", [
                                "Yth. {$record->nama},",
                                '',
                                'Permohonan Surat Izin Praktik Anda sedang diproses.',
                                'Mohon melengkapi berkas berikut:',
                                '',
                                $kebutuhan,
                                '',
                                'Anda bisa melengkapi berkas pada link berikut:',
                                $link,
                                '',
                                'Terima kasih.',
                                'DPMPTSP Kota Surabaya',
                            ]);
                        }),
                ])
                ->action(function (array $data) {
                    $record = $this->record;

                    if ($data['channel'] === 'whatsapp') {
                        $record->update(['follow_up_whatsapp_pada' => now()]);

                        $no = preg_replace('/[^0-9]/', '', $record->nomor_telepon);
                        $no = Str::startsWith($no, '0')
                            ? '62'.substr($no, 1)
                            : $no;

                        $pesan = rawurlencode($data['pesan']);

                        $this->js("window.open('https://wa.me/{$no}?text={$pesan}', '_blank')");

                        return;
                    }

                    if ($data['channel'] === 'email') {
                        $record->update(['follow_up_email_pada' => now()]);

                        $subject = rawurlencode('Tindak Lanjut Permohonan SIP');
                        $body = rawurlencode($data['pesan']);

                        $this->js(
                            "window.location.href = 'mailto:{$record->email}?subject={$subject}&body={$body}'"
                        );

                        return;
                    }
                }),

            /* ===============================
             * SELESAIKAN PERMOHONAN
             * =============================== */
            Action::make('selesai')
                ->label('Selesaikan Permohonan')
                ->icon(Heroicon::CheckCircle)
                ->color('success')
                ->requiresConfirmation()
                // Setelah dinas teknis menyetujui; 'proses' tetap diizinkan
                // untuk berkas yang tidak melewati SSW sama sekali.
                ->visible(fn () => in_array($this->record->status, ['proses', 'terverifikasi_teknis'], true))
                ->schema([
                    Textarea::make('catatan')
                        ->label('Catatan (opsional)')
                        ->rows(3),
                ])
                ->action(
                    fn (array $data) => $this->record->update([
                        'status' => 'selesai',
                        'keterangan' => $data['catatan'] ?? null,
                    ])
                ),

            /* ===============================
             * KIRIM KE SSW
             * =============================== */
            Action::make('kirimSsw')
                ->label('Kirim ke SSW')
                ->icon(Heroicon::CloudArrowUp)
                ->color('primary')
                // Aturan kelayakan tinggal di service supaya sama persis dengan
                // penyaringan bulk action di tabel.
                ->visible(fn () => app(SswMppdService::class)->bolehDikirim($this->record))
                ->modalHeading('Kirim Permohonan ke SSW')
                ->modalDescription('Data permohonan dikirim ke SSW, lalu berkas menunggu keputusan dinas teknis. Selama menunggu, berkas tidak bisa diubah dari sini.')
                ->modalSubmitActionLabel('Kirim')
                ->schema([
                    Select::make('id_ijin')
                        ->label('Jenis Izin SSW')
                        ->helperText('Daftar diambil dari master izin SSW (layanan MPPD).')
                        ->native(false)
                        ->searchable()
                        ->preload()
                        ->required()
                        ->options(fn () => app(SswMppdService::class)->opsiIzinAman())
                        ->default(fn () => $this->record->id_ijin ?? config('ssw.defaults.id_ijin')),

                    Select::make('jenis_permohonan')
                        ->label('Jenis Permohonan')
                        ->native(false)
                        ->required()
                        ->options(config('ssw.jenis_permohonan_options'))
                        ->default(fn () => $this->record->jenis_permohonan ?? config('ssw.defaults.jenis_permohonan')),
                ])
                ->action(function (array $data) {
                    $record = $this->record;

                    // Simpan pilihan petugas lebih dulu supaya tidak perlu
                    // diisi ulang kalau pengiriman gagal.
                    $record->update([
                        'id_ijin' => $data['id_ijin'],
                        'jenis_permohonan' => $data['jenis_permohonan'],
                    ]);

                    try {
                        $hasil = app(SswMppdService::class)->kirimDanCatat($record);
                    } catch (SswException $e) {
                        // Pesan kegagalan sudah dicatat ke kolom ssw_error
                        // oleh kirimDanCatat().
                        $detail = $e instanceof SswRequestException
                            ? collect($e->errorValidasi())
                                ->map(fn ($pesan, $field) => $field.': '.(is_array($pesan) ? implode(', ', $pesan) : $pesan))
                                ->implode(' ')
                            : '';

                        Notification::make()
                            ->title('Gagal mengirim ke SSW')
                            ->body(trim($e->pesanUntukPengguna().' '.$detail))
                            ->danger()
                            ->persistent()
                            ->send();

                        return;
                    }

                    Notification::make()
                        ->title('Permohonan terkirim ke SSW')
                        ->body('ID permohonan SSW: '.($hasil->idPermohonanDetail() ?? '-')
                            .'. Berkas kini menunggu verifikasi teknis.')
                        ->success()
                        ->send();
                }),

            /* ===============================
             * KEPUTUSAN
             * =============================== */
            ActionGroup::make([
                Action::make('tolak')
                    ->label('Tolak Permohonan')
                    ->icon(Heroicon::XCircle)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->schema([
                        Textarea::make('alasan')
                            ->label('Alasan Penolakan')
                            ->required(),
                    ])
                    ->action(
                        fn (array $data) => $this->record->update([
                            'status' => 'ditolak',
                            'keterangan' => $data['alasan'],
                        ])
                    ),

                Action::make('batalkan')
                    ->label('Batalkan Permohonan')
                    ->icon(Heroicon::MinusCircle)
                    ->color('gray')
                    ->requiresConfirmation()
                    ->schema([
                        Textarea::make('alasan')
                            ->label('Alasan Pembatalan'),
                    ])
                    ->action(
                        fn (array $data) => $this->record->update([
                            'status' => 'dibatalkan',
                            'keterangan' => $data['alasan'] ?? null,
                        ])
                    ),
            ])
                ->label('Tolak/Batal')
                ->button()
                ->color(Color::Red)
                ->icon(Heroicon::EllipsisVertical)
                ->visible(
                    fn () => in_array(
                        $this->record->status,
                        ['masuk', 'proses', 'terverifikasi_teknis', 'ditolak_teknis'],
                        true
                    )
                ),

            /* ===============================
             * HISTORY
             * =============================== */
            Action::make('history')
                ->label('History Perubahan')
                ->icon(Heroicon::Clock)
                ->url(
                    ListSuratIzinPraktiksActivities::getUrl([
                        'record' => $this->record,
                    ])
                ),
        ];
    }

    /** Cache per-request untuk daftar pengajuan lama yang relevan. */
    protected ?\Illuminate\Support\Collection $riwayatCache = null;

    /**
     * Pengajuan lain milik pemohon yang sama (cocok NIK) yang memiliki
     * minimal satu dokumen dengan name yang cocok dengan kebutuhan_upload
     * pengajuan ini. Terbaru lebih dulu.
     */
    protected function riwayatPengajuan(): \Illuminate\Support\Collection
    {
        if ($this->riwayatCache !== null) {
            return $this->riwayatCache;
        }

        $kebutuhanNames = collect($this->record->kebutuhan_upload ?? [])
            ->pluck('name')
            ->filter()
            ->all();

        if (empty($kebutuhanNames) || blank($this->record->nik)) {
            return $this->riwayatCache = collect();
        }

        return $this->riwayatCache = SuratIzinPraktik::query()
            ->whereKeyNot($this->record->getKey())
            ->where('nik', $this->record->nik)
            ->latest()
            ->get()
            ->filter(
                fn ($r) => collect($r->document_upload ?? [])
                    ->contains(
                        fn ($doc) => isset($doc['name'])
                            && in_array($doc['name'], $kebutuhanNames, true)
                    )
            )
            ->values();
    }
}
