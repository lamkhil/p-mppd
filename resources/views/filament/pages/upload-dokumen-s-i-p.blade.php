<x-filament-panels::page.simple>
    <div class="space-y-6">

        {{-- ===============================
            INFORMASI PERMOHONAN
        =============================== --}}
        <x-filament::section heading="Informasi Permohonan">
            <dl class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                <div>
                    <dt class="font-medium text-gray-600">Nomor Register</dt>
                    <dd class="mt-1">{{ $this->record->nomor_register }}</dd>
                </div>

                <div>
                    <dt class="font-medium text-gray-600">Nama Pemohon</dt>
                    <dd class="mt-1">{{ $this->record->nama }}</dd>
                </div>

                <div>
                    <dt class="font-medium text-gray-600">Profesi</dt>
                    <dd class="mt-1">{{ $this->record->profesi }}</dd>
                </div>

                <div class="md:col-span-2">
                    <dt class="font-medium text-gray-600">Tempat Praktik</dt>
                    <dd class="mt-1">{{ $this->record->tempat_praktik }}</dd>
                </div>
            </dl>
        </x-filament::section>

        {{-- ===============================
            CATATAN / KETERANGAN (JIKA ADA)
        =============================== --}}
        @if($this->record->keterangan)
            <x-filament::section heading="Catatan dari Petugas">
                <p class="text-sm whitespace-pre-line">
                    {{ $this->record->keterangan }}
                </p>
            </x-filament::section>
        @endif

        {{-- ===============================
            FORM UPLOAD DOKUMEN
        =============================== --}}
        <x-filament::section heading="Upload Dokumen yang Diminta">
            <form wire:submit.prevent="submit" class="space-y-6">
                {{ $this->form }}

                <x-filament::button type="submit" color="primary">
                    Upload Dokumen
                </x-filament::button>
            </form>
        </x-filament::section>

    </div>
</x-filament-panels::page.simple>
