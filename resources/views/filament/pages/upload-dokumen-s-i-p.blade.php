<x-filament-panels::page.simple>
    <div class="space-y-6">

        {{-- ===============================
            HEADER / BRAND
        =============================== --}}
        <div class="flex items-center justify-between rounded-xl border border-slate-200 bg-white px-5 py-4 shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-blue-700 dark:text-blue-300">
                    P-MPPD · DPMPTSP Surabaya
                </p>
                <h1 class="mt-1 text-lg font-semibold text-slate-900 dark:text-white">
                    {{ $isAdmin ? 'Tinjau Dokumen Pemohon' : 'Unggah Dokumen Surat Izin Praktik' }}
                </h1>
            </div>

            @if ($isAdmin)
                <span class="inline-flex items-center gap-2 rounded-lg bg-amber-50 px-3 py-2 text-xs font-semibold text-amber-700 dark:bg-amber-900/30 dark:text-amber-300">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                    </svg>
                    Mode Admin · Read-only
                </span>
            @else
                <span class="hidden sm:inline-flex items-center gap-2 rounded-lg bg-blue-50 px-3 py-2 text-xs font-semibold text-blue-700 dark:bg-blue-900/30 dark:text-blue-300">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                    </svg>
                    Form Resmi
                </span>
            @endif
        </div>

        {{-- ===============================
            INFORMASI PERMOHONAN
        =============================== --}}
        <x-filament::section heading="Informasi Permohonan" icon="heroicon-o-identification">
            <dl class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-4 text-sm">
                <div>
                    <dt class="font-medium text-slate-500 dark:text-slate-400">Nomor Register</dt>
                    <dd class="mt-1 font-semibold text-slate-900 dark:text-white">{{ $this->record->nomor_register }}</dd>
                </div>

                <div>
                    <dt class="font-medium text-slate-500 dark:text-slate-400">Nama Pemohon</dt>
                    <dd class="mt-1 text-slate-900 dark:text-white">{{ $this->record->nama }}</dd>
                </div>

                <div>
                    <dt class="font-medium text-slate-500 dark:text-slate-400">Profesi</dt>
                    <dd class="mt-1 text-slate-900 dark:text-white">{{ $this->record->profesi ?: '—' }}</dd>
                </div>

                <div>
                    <dt class="font-medium text-slate-500 dark:text-slate-400">Status Berkas</dt>
                    <dd class="mt-1">
                        @php
                            $statusLabels = [
                                'masuk' => 'Masuk',
                                'proses' => 'Proses',
                                'selesai' => 'Selesai',
                                'terverifikasi_teknis' => 'Terverifikasi Teknis',
                                'ditolak_teknis' => 'Ditolak Teknis',
                                'ditolak' => 'Ditolak',
                                'dibatalkan' => 'Dibatalkan',
                            ];
                            $statusColor = match ($this->record->status) {
                                'masuk' => 'bg-amber-100 text-amber-800',
                                'proses' => 'bg-sky-100 text-sky-800',
                                'selesai', 'terverifikasi_teknis' => 'bg-emerald-100 text-emerald-800',
                                'ditolak_teknis', 'ditolak' => 'bg-red-100 text-red-800',
                                default => 'bg-slate-100 text-slate-700',
                            };
                        @endphp
                        <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $statusColor }}">
                            {{ $statusLabels[$this->record->status] ?? ucfirst((string) $this->record->status) }}
                        </span>
                    </dd>
                </div>

                <div class="md:col-span-2">
                    <dt class="font-medium text-slate-500 dark:text-slate-400">Tempat Praktik</dt>
                    <dd class="mt-1 text-slate-900 dark:text-white">{{ $this->record->tempat_praktik ?: '—' }}</dd>
                </div>
            </dl>
        </x-filament::section>

        {{-- ===============================
            CATATAN / KETERANGAN (JIKA ADA)
        =============================== --}}
        @if ($this->record->keterangan)
            <x-filament::section heading="Catatan dari Petugas" icon="heroicon-o-chat-bubble-bottom-center-text">
                <p class="text-sm whitespace-pre-line text-slate-700 dark:text-slate-300">
                    {{ $this->record->keterangan }}
                </p>
            </x-filament::section>
        @endif

        {{-- ===============================
            (PUBLIK) VERIFIKASI NIK
        =============================== --}}
        @if (! $verified && ! $isAdmin)
            <x-filament::section
                heading="Verifikasi Identitas"
                icon="heroicon-o-shield-check"
                description="Masukkan NIK pemohon untuk membuka form unggah dokumen.">
                <form wire:submit.prevent="verifyNik" class="space-y-4">
                    {{ $this->verificationForm }}

                    <div class="flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:justify-end pt-2 border-t border-slate-200 dark:border-slate-700">
                        <p class="text-xs text-slate-500 dark:text-slate-400 sm:mr-auto">
                            NIK Anda hanya digunakan untuk verifikasi dan tidak disimpan ulang.
                        </p>
                        <x-filament::button type="submit" color="primary" icon="heroicon-m-lock-open">
                            Verifikasi & Buka Form
                        </x-filament::button>
                    </div>
                </form>
            </x-filament::section>
        @else
            {{-- ===============================
                FORM UPLOAD / TAMPILAN ADMIN
            =============================== --}}
            <x-filament::section
                :heading="$isAdmin ? 'Dokumen Pemohon' : 'Unggah Dokumen yang Diminta'"
                :icon="$isAdmin ? 'heroicon-o-document-magnifying-glass' : 'heroicon-o-arrow-up-tray'"
                :description="$isAdmin
                    ? 'Mode admin · semua field dalam keadaan tidak aktif. Klik file untuk membuka berkas pemohon.'
                    : 'Pastikan dokumen sudah benar dan jelas terbaca sebelum dikirim.'">
                <form wire:submit.prevent="submit" class="space-y-6">
                    {{ $this->form }}

                    @unless ($isAdmin)
                        <div class="flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:justify-end pt-2 border-t border-slate-200 dark:border-slate-700">
                            <p class="text-xs text-slate-500 dark:text-slate-400 sm:mr-auto">
                                Data Anda dilindungi sesuai ketentuan DPMPTSP Surabaya.
                            </p>
                            <x-filament::button type="submit" color="primary" icon="heroicon-m-paper-airplane">
                                Kirim Dokumen
                            </x-filament::button>
                        </div>
                    @endunless
                </form>
            </x-filament::section>
        @endif

        <p class="text-center text-xs text-slate-500 dark:text-slate-400">
            © {{ date('Y') }} DPMPTSP Kota Surabaya · Sistem P-MPPD
        </p>
    </div>
</x-filament-panels::page.simple>
