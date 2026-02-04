<?php

namespace App\Filament\Pages;

use App\Models\SuratIzinPraktik;
use Filament\Pages\SimplePage;
use Filament\Forms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Schema;

class UploadDokumenSIP extends SimplePage implements HasForms
{
    use InteractsWithForms;

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.pages.upload-dokumen-s-i-p';

    public array $data = [];

    public SuratIzinPraktik $record;

    public static function canAccess(): bool
    {
        return true; // PUBLIC
    }

    public function mount(SuratIzinPraktik $record): void
    {
        $this->record = $record;
        $existing = collect($this->record->document_upload ?? [])
            ->mapWithKeys(function ($doc) {
                return [
                    $doc['name'] => $doc['file'] ?? $doc['value'] ?? null,
                ];
            })
            ->toArray();

        $this->form->fill($existing);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->schema([
                ...$this->generateDocumentUpload()
            ]);
    }

    /**
     * Generate dynamic document upload fields
     */
    protected function generateDocumentUpload(): array
    {
        return collect($this->record->kebutuhan_upload ?? [])
            ->map(function ($item) {

                return match ($item['type']) {

                    'text' => Forms\Components\TextInput::make($item['name'])
                        ->label($item['name'])
                        ->helperText($item['description'])
                        ->required(),

                    'date' => Forms\Components\DatePicker::make($item['name'])
                        ->label($item['name'])
                        ->helperText($item['description'])
                        ->required(),

                    'pdf' => Forms\Components\FileUpload::make($item['name'])
                        ->label($item['name'])
                        ->openable()
                        ->downloadable()
                        ->moveFiles()
                        ->acceptedFileTypes([
                            'application/pdf'
                        ])
                        ->helperText($item['description'])
                        ->directory('sip-upload')
                        ->required(),
                    'image'  => Forms\Components\FileUpload::make($item['name'])
                        ->label($item['name'])
                        ->image()
                        ->openable()
                        ->downloadable()
                        ->helperText($item['description'])
                        ->directory('sip-upload')
                        ->required(),

                    'file' => Forms\Components\FileUpload::make($item['name'])
                        ->label($item['name'])
                        ->openable()
                        ->downloadable()
                        ->helperText($item['description'])
                        ->directory('sip-upload')
                        ->required(),

                    default => null,
                };
            })
            ->filter()
            ->values()
            ->toArray();
    }

    public function submit(): void
    {
        $data = $this->form->getState();

        $documents = collect($this->record->kebutuhan_upload ?? [])
            ->map(function ($need) use ($data) {
                $name = $need['name'];

                if (!isset($data[$name])) {
                    return null;
                }

                return [
                    'name'        => $name,
                    'type'        => $need['type'],
                    'value'       => in_array($need['type'], ['text', 'date'])
                        ? $data[$name]
                        : null,
                    'file'        => in_array($need['type'], ['pdf', 'image', 'file'])
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
            ->body('Sudah berhasil upload data')
            ->success()
            ->send();
    }
}
