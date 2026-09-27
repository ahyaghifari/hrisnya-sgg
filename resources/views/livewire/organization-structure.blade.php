<div>
<x-page-header title="Struktur Organisasi" />

    <div class="mb-6 max-w-sm">
        <label class="form-label">Pilih Perusahaan</label>
        <select wire:model.live="companyId" class="form-input">
            <option value="">Pilih perusahaan...</option>
            @foreach ($this->companyOptions as $id => $name)
                <option value="{{ $id }}">{{ $name }}</option>
            @endforeach
        </select>
    </div>

<livewire:organization-chart-widget :company-id="$companyId" :key="'org-chart-'.$companyId" />

{{-- Panel detail jabatan - slide dari kanan (docked di desktop, full-screen di HP, sama pola
dengan panel Riwayat Merit Point/Riwayat Perubahan Data di employee-statistics.blade.php), pakai
warna tema situs sendiri (var(--surface) dkk, ikut dark mode) - sebelumnya x-filament::section
bawaan Filament (primary-*/gray-*), kepakai di sini walau bukan admin panel jadi tampilannya
nyempil sendiri & tidak ikut dark mode. Alpine (bukan Livewire @if polos) sengaja dipertahankan -
satu-satunya cara dapat animasi KELUAR juga (bukan cuma masuk), Livewire @if langsung mencabut
DOM tanpa transisi keluar. --}}
<div x-data="{ open: false }" x-on:open-job-detail.window="open = true" x-on:keydown.escape.window="open = false">
    <div
        x-show="open"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        x-on:click="open = false"
        class="fixed inset-0 bg-black/50 z-[9999]"
        style="display: none;"
    ></div>

    <div
        x-show="open"
        x-transition:enter="transition ease-out duration-[250ms]"
        x-transition:enter-start="translate-x-full opacity-0"
        x-transition:enter-end="translate-x-0 opacity-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="translate-x-0 opacity-100"
        x-transition:leave-end="translate-x-full opacity-0"
        class="fixed top-0 end-0 h-full w-full sm:w-[480px] sm:max-w-[90vw] bg-[var(--surface)] shadow-[-8px_0_28px_rgba(0,0,0,0.18)] flex flex-col z-[10000]"
        style="display: none;"
    >
        <div class="flex items-center justify-between py-4 px-5 border-b border-[var(--border-soft)] shrink-0">
            <h3 class="font-bold text-[15.5px] text-[var(--text-heading)]">Detail Jabatan</h3>
            <button type="button" class="flex items-center justify-center h-8 w-8 rounded-full text-[var(--text-faint)] hover:bg-[var(--surface-alt)]" x-on:click="open = false" aria-label="Tutup">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <div class="p-4 sm:p-5 overflow-y-auto grow" style="padding-bottom: calc(20px + env(safe-area-inset-bottom));">
    @if ($this->selectedJobDetail)
        <div class="flex flex-col gap-4">
            {{-- Informasi Dasar Jabatan --}}
            <div class="form-card" style="margin:0;padding:18px;">
                <div class="flex items-center gap-3 mb-4">
                    <div class="h-12 w-12 rounded-full flex items-center justify-center flex-shrink-0" style="background:rgba(4,44,108,0.1);color:var(--brand);">
                        <i class="fa-solid fa-briefcase text-lg"></i>
                    </div>
                    <div class="min-w-0">
                        <h3 class="text-lg font-bold text-[var(--text-heading)] truncate">{{ $this->selectedJobDetail->name }}</h3>
                        <p class="text-sm text-[var(--text-muted)] truncate">{{ $this->selectedJobDetail->company->name ?? '-' }}</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div class="p-3 rounded-xl" style="background:var(--surface-alt);border:1px solid var(--border-soft);">
                        <span class="block text-[11px] font-medium text-[var(--text-faint)] mb-1">Unit Organisasi</span>
                        <span class="block text-sm font-semibold text-[var(--text-heading)]">{{ $this->selectedJobDetail->orgStructure?->name ?? 'Jabatan Struktural' }}</span>
                    </div>

                    <div class="p-3 rounded-xl" style="background:var(--surface-alt);border:1px solid var(--border-soft);">
                        <span class="block text-[11px] font-medium text-[var(--text-faint)] mb-1">Atasan Langsung</span>
                        <span class="block text-sm font-semibold text-[var(--text-heading)]">{{ $this->selectedJobDetail->parent?->name ?? 'Tidak ada' }}</span>
                    </div>
                </div>
            </div>

            {{-- Klasifikasi Jabatan --}}
            <div class="form-card" style="margin:0;padding:18px;">
                <div class="section-head" style="margin-bottom:12px;">
                    <div class="section-icon"><i class="fa-solid fa-layer-group" style="color:var(--brand);font-size:12px;"></i></div>
                    <span class="section-title">Klasifikasi Jabatan</span>
                </div>

                <div class="grid grid-cols-3 gap-2">
                    <div class="p-3 rounded-lg text-center" style="border:1px solid var(--border-soft);">
                        <span class="block text-[10.5px] text-[var(--text-faint)] uppercase tracking-wider">Pangkat</span>
                        <span class="block mt-1 font-semibold text-[var(--text-heading)] text-sm">{{ $this->selectedJobDetail->pangkat?->name ?? '-' }}</span>
                    </div>
                    <div class="p-3 rounded-lg text-center" style="border:1px solid var(--border-soft);">
                        <span class="block text-[10.5px] text-[var(--text-faint)] uppercase tracking-wider">Golongan</span>
                        <span class="block mt-1 font-semibold text-[var(--text-heading)] text-sm">{{ $this->selectedJobDetail->pangkat?->golongan ?? '-' }}</span>
                    </div>
                    <div class="p-3 rounded-lg text-center" style="border:1px solid var(--border-soft);">
                        <span class="block text-[10.5px] text-[var(--text-faint)] uppercase tracking-wider">Kelas</span>
                        <span class="block mt-1 font-semibold text-[var(--text-heading)] text-sm">{{ $this->selectedJobDetail->kelas?->name ?? '-' }}</span>
                    </div>
                </div>
            </div>

            {{-- Pemegang Jabatan - foto profil kalau sudah upload, kalau belum inisial nama
            (x-avatar, sama komponen yang dipakai welcome-card/dsb). --}}
            <div class="form-card" style="margin:0;padding:18px;">
                <div class="section-head" style="margin-bottom:10px;">
                    <div class="section-icon"><i class="fa-solid fa-user-group" style="color:var(--brand);font-size:12px;"></i></div>
                    <span class="section-title">Pemegang Jabatan ({{ $this->selectedJobDetail->userAssignments->count() }})</span>
                </div>

                @if ($this->selectedJobDetail->userAssignments->isNotEmpty())
                    <div class="pwa-list">
                        @foreach ($this->selectedJobDetail->userAssignments as $assignment)
                            @continue(!$assignment->user)
                            <div class="pwa-list-row">
                                <x-avatar :user="$assignment->user" :size="36" />
                                <div class="min-w-0">
                                    <div class="pwa-row-title truncate">{{ $assignment->user->name }}</div>
                                    <span style="font-size:11.5px;color:var(--text-muted);" class="truncate block">{{ $assignment->user->email }}</span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="text-sm text-[var(--text-muted)]">Belum ada user yang menempati jabatan ini.</p>
                @endif
            </div>

            {{-- Sub Jabatan --}}
            @if ($this->selectedJobDetail->children && $this->selectedJobDetail->children->count() > 0)
                <div class="form-card" style="margin:0;padding:18px;">
                    <div class="section-head" style="margin-bottom:10px;">
                        <div class="section-icon"><i class="fa-solid fa-sitemap" style="color:var(--brand);font-size:12px;"></i></div>
                        <span class="section-title">Sub Jabatan Bawahan ({{ $this->selectedJobDetail->children->count() }})</span>
                    </div>

                    <div class="pwa-list">
                        @foreach ($this->selectedJobDetail->children as $child)
                            <div class="pwa-list-row" style="cursor:pointer;" wire:click="openJobDetail('{{ $child->id }}')">
                                <div class="pwa-tile-icon neutral"><i class="fa-solid fa-briefcase"></i></div>
                                <div class="min-w-0 flex-1">
                                    <div class="pwa-row-title truncate">{{ $child->name }}</div>
                                    @if ($child->orgStructure)
                                        <span style="font-size:11.5px;color:var(--brand);font-weight:600;" class="truncate block">{{ $child->orgStructure->name }}</span>
                                    @endif
                                </div>
                                <i class="fa-solid fa-chevron-right"></i>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            <button type="button" x-on:click="open = false" class="btn-secondary" style="width:100%;justify-content:center;">
                Tutup
            </button>
        </div>
    @endif
        </div>
    </div>
</div>

</div>
