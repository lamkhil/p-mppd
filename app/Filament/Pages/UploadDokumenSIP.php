<?php

namespace App\Filament\Pages;

use App\Models\SuratIzinPraktik;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\SimplePage;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class UploadDokumenSIP extends SimplePage implements HasForms
{
    use InteractsWithForms;

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.pages.upload-dokumen-s-i-p';

    public array $data = [];

    public array $verificationData = [];

    public bool $verified = false;

    public bool $isAdmin = false;

    public int $attempts = 0;

    public SuratIzinPraktik $record;

    public static function canAccess(): bool
    {
        return true; // PUBLIC
    }

    public function mount(SuratIzinPraktik $record): void
    {
        $this->record = $record;
        $this->isAdmin = Auth::check();

        // Admin: bypass verifikasi, langsung lihat (read-only)
        if ($this->isAdmin) {
            $this->verified = true;
            $this->fillUploadForm();

            return;
        }

        // Public: form upload kosong dulu, baru aktif setelah NIK cocok
        $this->verificationForm->fill();
    }

    protected function getForms(): array
    {
        return [
            'verificationForm',
            'form',
        ];
    }

    /* ===========================================================
     *  FORM 1 — Verifikasi NIK (hanya untuk pengguna publik)
     * =========================================================== */
    public function verificationForm(Schema $schema): Schema
    {
        return $schema
            ->statePath('verificationData')
            ->components([
                Forms\Components\TextInput::make('nik')
                    ->label('NIK Pemohon')
                    ->placeholder('Contoh: 3578xxxxxxxxxxxx')
                    ->helperText('Masukkan NIK yang terdaftar pada permohonan untuk membuka form unggah.')
                    ->required()
                    ->maxLength(20)
                    ->extraInputAttributes([
                        'inputmode' => 'numeric',
                        'autocomplete' => 'off',
                    ]),
            ]);
    }

    public function verifyNik(): void
    {
        $data = $this->verificationForm->getState();

        $input = preg_replace('/\D+/', '', (string) ($data['nik'] ?? ''));
        $expected = preg_replace('/\D+/', '', (string) $this->record->nik);

        if ($input !== '' && $input === $expected) {
            $this->verified = true;
            $this->fillUploadForm();

            Notification::make()
                ->title('Verifikasi berhasil')
                ->body('Silakan unggah dokumen yang diminta di bawah.')
                ->success()
                ->send();

            return;
        }

        $this->attempts++;

        Notification::make()
            ->title('NIK tidak cocok')
            ->body('Pastikan NIK Anda sesuai dengan data permohonan. Percobaan: '.$this->attempts)
            ->danger()
            ->send();
    }

    /* ===========================================================
     *  FORM 2 — Upload dokumen (dinamis dari kebutuhan_upload)
     * =========================================================== */
    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components($this->generateDocumentUpload());
    }

    protected function fillUploadForm(): void
    {
        $existing = collect($this->record->document_upload ?? [])
            ->mapWithKeys(function ($doc) {
                return [
                    $doc['name'] => $doc['file'] ?? $doc['value'] ?? null,
                ];
            })
            ->toArray();

        $this->form->fill($existing);
    }

    /**
     * Generate field dinamis untuk tiap item kebutuhan_upload.
     * Field di-disable kalau user adalah admin (read-only mode).
     */
    protected function generateDocumentUpload(): array
    {
        $disabled = false;

        return collect($this->record->kebutuhan_upload ?? [])
            ->map(function ($item) use ($disabled) {
                return match ($item['type']) {
                    'text' => Forms\Components\TextInput::make($item['name'])
                        ->label($item['name'])
                        ->helperText($item['description'])
                        ->disabled($disabled)
                        ->dehydrated(! $disabled)
                        ->required(! $disabled),

                    'date' => Forms\Components\DatePicker::make($item['name'])
                        ->label($item['name'])
                        ->helperText($item['description'])
                        ->disabled($disabled)
                        ->dehydrated(! $disabled)
                        ->required(! $disabled),

                    'pdf' => Forms\Components\FileUpload::make($item['name'])
                        ->label($item['name'])
                        ->openable()
                        ->downloadable()
                        ->moveFiles()
                        ->acceptedFileTypes(['application/pdf'])
                        ->helperText($item['description'])
                        ->directory('sip-upload')
                        ->disabled($disabled)
                        ->dehydrated(! $disabled)
                        ->required(! $disabled),

                    'image' => Forms\Components\FileUpload::make($item['name'])
                        ->label($item['name'])
                        ->image()
                        ->openable()
                        ->downloadable()
                        ->helperText($item['description'])
                        ->directory('sip-upload')
                        ->disabled($disabled)
                        ->dehydrated(! $disabled)
                        ->required(! $disabled),

                    'file' => Forms\Components\FileUpload::make($item['name'])
                        ->label($item['name'])
                        ->openable()
                        ->downloadable()
                        ->helperText($item['description'])
                        ->directory('sip-upload')
                        ->disabled($disabled)
                        ->dehydrated(! $disabled)
                        ->required(! $disabled),

                    default => null,
                };
            })
            ->filter()
            ->values()
            ->toArray();
    }

    /* ===========================================================
     *  SUBMIT — hanya pengguna publik yang sudah terverifikasi
     * =========================================================== */
    public function submit(): void
    {
        if ($this->isAdmin) {
            Notification::make()
                ->title('Mode admin')
                ->body('Admin tidak dapat mengunggah berkas dari halaman ini.')
                ->warning()
                ->send();

            return;
        }

        if (! $this->verified) {
            Notification::make()
                ->title('Belum terverifikasi')
                ->body('Verifikasi NIK terlebih dahulu sebelum mengunggah.')
                ->danger()
                ->send();

            return;
        }

        $data = $this->form->getState();

        $documents = collect($this->record->kebutuhan_upload ?? [])
            ->map(function ($need) use ($data) {
                $name = $need['name'];

                if (! isset($data[$name])) {
                    return null;
                }

                return [
                    'name' => $name,
                    'type' => $need['type'],
                    'value' => in_array($need['type'], ['text', 'date'])
                        ? $data[$name]
                        : null,
                    'file' => in_array($need['type'], ['pdf', 'image', 'file'])
                        ? $data[$name]
                        : null,
                    'uploaded_at' => now(),
                ];
            })
            ->filter()
            ->values()
            ->toArray();

        $this->record->update([
            'document_upload' => $documents,
        ]);

        Notification::make('success')
            ->title('Berhasil')
            ->body('Dokumen Anda berhasil diunggah.')
            ->success()
            ->send();
    }
}
