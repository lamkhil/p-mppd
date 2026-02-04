<?php

namespace App\Filament\Resources\SuratIzinPraktik\Pages;

use App\Filament\Resources\SuratIzinPraktik\SuratIzinPraktikResource;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Resources\Pages\ViewRecord;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
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
                ->visible(fn() => $this->record->status === 'masuk')
                ->schema([
                    Checkbox::make('need_update')
                        ->label('Perlu melengkapi berkas?')
                        ->live(),

                    Repeater::make('kebutuhan_upload')
                        ->visible(fn($get) => $get('need_update') === true)
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
                                    'text'  => 'Teks',
                                    'date'  => 'Tanggal',
                                    'pdf'   => 'PDF',
                                    'image' => 'Gambar',
                                    'file'  => 'File Lainnya',
                                ]),

                            TextInput::make('description')
                                ->label('Penjelasan')
                                ->columnSpanFull(),
                        ]),

                    Textarea::make('catatan')
                        ->label('Catatan Petugas'),
                ])
                ->action(
                    fn(array $data) =>
                    $this->record->update([
                        'status'           => 'proses',
                        'kebutuhan_upload' => $data['need_update']
                            ? $data['kebutuhan_upload']
                            : null,
                        'keterangan'       => $data['catatan'] ?? $this->record->catatan,
                    ])
                ),

            /* ===============================
             * FOLLOW UP PEMOHON
             * =============================== */
            Action::make('followUp')
                ->label('Follow Up Pemohon')
                ->icon(Heroicon::ChatBubbleLeftRight)
                ->color('warning')
                ->visible(
                    fn() =>
                    $this->record->status === 'proses'
                        && !empty($this->record->kebutuhan_upload)
                )
                ->schema([
                    Radio::make('channel')
                        ->label('Media Follow Up')
                        ->required()
                        ->options([
                            'whatsapp' => 'WhatsApp',
                            'email'    => 'Email',
                        ]),

                    Textarea::make('pesan')
                        ->label('Pesan')
                        ->rows(6)
                        ->default(function () {
                            $record = $this->record;

                            $link = route('sip.upload', [
                                'record' => $record
                            ]);

                            $kebutuhan = collect($record->kebutuhan_upload ?? [])
                                ->pluck('name')
                                ->map(fn($item, $i) => ($i + 1) . '. ' . $item)
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
                            ? '62' . substr($no, 1)
                            : $no;

                        $pesan = rawurlencode($data['pesan']);

                        $this->js("window.open('https://wa.me/{$no}?text={$pesan}', '_blank')");
                        return;
                    }

                    if ($data['channel'] === 'email') {
                        $record->update(['follow_up_email_pada' => now()]);

                        $subject = rawurlencode('Tindak Lanjut Permohonan SIP');
                        $body    = rawurlencode($data['pesan']);

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
                ->visible(fn() => $this->record->status === 'proses')
                ->schema([
                    Textarea::make('catatan')
                        ->label('Catatan (opsional)')
                        ->rows(3),
                ])
                ->action(
                    fn(array $data) =>
                    $this->record->update([
                        'status'     => 'selesai',
                        'keterangan' => $data['catatan'] ?? null,
                    ])
                ),

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
                        fn(array $data) =>
                        $this->record->update([
                            'status'     => 'ditolak',
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
                        fn(array $data) =>
                        $this->record->update([
                            'status'     => 'dibatalkan',
                            'keterangan' => $data['alasan'] ?? null,
                        ])
                    ),
            ])
                ->label('Tolak/Batal')
                ->button()
                ->color(Color::Red)
                ->icon(Heroicon::EllipsisVertical)
                ->visible(
                    fn() =>
                    in_array($this->record->status, ['masuk', 'proses'])
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
}
