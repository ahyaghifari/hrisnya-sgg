<div>
@push('head')
    @filamentStyles
    <link rel="stylesheet" href="{{ asset('css/filament/filament/app.css') }}">
@endpush

<div class="mb-6 flex items-center justify-between">
    <div>
        <p class="text-xs text-slate-500 mb-1">{{ now()->translatedFormat('l, d F Y') }}</p>
        <h1 class="text-[22px] font-extrabold text-slate-900 tracking-tight">Struktur Organisasi</h1>
    </div>
</div>

@if ($this->showCompanyFilter)
    <div class="mb-6 max-w-sm">
        <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wide mb-1.5">Pilih Perusahaan</label>
        <select wire:model.live="companyId" class="w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-slate-400 focus:ring-slate-400">
            <option value="">Pilih perusahaan...</option>
            @foreach ($this->companyOptions as $id => $name)
                <option value="{{ $id }}">{{ $name }}</option>
            @endforeach
        </select>
    </div>
@endif

<livewire:organization-chart-widget :company-id="$companyId" :key="'org-chart-'.$companyId" />

{{-- Slideover: Job Position Detail (Alpine, bundled with Livewire — no extra dependency) --}}
<div x-data="{ open: false }" x-on:open-job-detail.window="open = true" x-on:keydown.escape.window="open = false">
    <div
        x-show="open"
        x-transition.opacity
        x-on:click="open = false"
        class="fixed inset-0 bg-gray-900/50 z-[80]"
        style="display: none;"
    ></div>

    <div
        x-show="open"
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="translate-x-full"
        x-transition:enter-end="translate-x-0"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="translate-x-0"
        x-transition:leave-end="translate-x-full"
        class="fixed top-0 end-0 h-full max-w-2xl w-full bg-white border-s border-gray-200 shadow-xl flex flex-col z-[80]"
        style="display: none;"
    >
        <div class="flex items-center justify-between py-3 px-4 border-b border-gray-200 shrink-0">
            <h3 class="font-bold text-gray-800">Detail Jabatan</h3>
            <button type="button" class="flex items-center justify-center h-8 w-8 rounded-full text-gray-500 hover:bg-gray-100" x-on:click="open = false">
                <span class="sr-only">Tutup</span>
                <x-heroicon-m-x-mark class="w-5 h-5" />
            </button>
        </div>

        <div class="p-4 overflow-y-auto grow">
    @if ($this->selectedJobDetail)
        <div class="space-y-6">
            {{-- Informasi Dasar Jabatan --}}
            <x-filament::section>
                <div class="flex items-center gap-4 mb-4">
                    <div class="h-12 w-12 rounded-full bg-primary-100 flex items-center justify-center text-primary-600">
                        <x-heroicon-o-briefcase class="w-6 h-6" />
                    </div>
                    <div>
                        <h3 class="text-xl font-bold text-gray-950">{{ $this->selectedJobDetail->name }}</h3>
                        <p class="text-sm text-gray-500">{{ $this->selectedJobDetail->company->name ?? '-' }}</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 mt-4">
                    <div class="p-4 rounded-xl bg-gray-50 border border-gray-200">
                        <span class="block text-xs font-medium text-gray-500 mb-1">Unit Organisasi</span>
                        <span class="block text-sm font-semibold text-gray-950">{{ $this->selectedJobDetail->orgStructure?->name ?? 'Jabatan Struktural' }}</span>
                    </div>

                    <div class="p-4 rounded-xl bg-gray-50 border border-gray-200">
                        <span class="block text-xs font-medium text-gray-500 mb-1">Atasan Langsung</span>
                        <span class="block text-sm font-semibold text-gray-950">{{ $this->selectedJobDetail->parent?->name ?? 'Tidak ada' }}</span>
                    </div>
                </div>
            </x-filament::section>

            {{-- Klasifikasi Jabatan --}}
            <x-filament::section>
                <x-slot name="heading">Klasifikasi Jabatan</x-slot>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3 mt-2">
                    <div class="p-3 rounded-lg border border-gray-200 flex flex-col items-center justify-center text-center">
                        <span class="text-xs text-gray-500 uppercase tracking-wider">Pangkat</span>
                        <span class="mt-1 flex-1 font-semibold text-gray-900">{{ $this->selectedJobDetail->pangkat?->name ?? '-' }}</span>
                    </div>
                    <div class="p-3 rounded-lg border border-gray-200 flex flex-col items-center justify-center text-center">
                        <span class="text-xs text-gray-500 uppercase tracking-wider">Golongan</span>
                        <span class="mt-1 flex-1 font-semibold text-gray-900">{{ $this->selectedJobDetail->golongan?->name ?? '-' }}</span>
                    </div>
                    <div class="p-3 rounded-lg border border-gray-200 flex flex-col items-center justify-center text-center">
                        <span class="text-xs text-gray-500 uppercase tracking-wider">Kelas</span>
                        <span class="mt-1 flex-1 font-semibold text-gray-900">{{ $this->selectedJobDetail->kelas?->name ?? '-' }}</span>
                    </div>
                </div>
            </x-filament::section>

            {{-- Pemegang Jabatan --}}
            <x-filament::section>
                <x-slot name="heading">Pemegang Jabatan ({{ $this->selectedJobDetail->userAssignments->count() }})</x-slot>

                @if($this->selectedJobDetail->userAssignments->isNotEmpty())
                    <div class="space-y-3 mt-2">
                        @foreach($this->selectedJobDetail->userAssignments as $assignment)
                            @continue(!$assignment->user)
                            <div class="flex items-center gap-3 p-3 bg-white border border-gray-200 rounded-xl">
                                <div class="h-8 w-8 rounded-full bg-gray-100 flex items-center justify-center text-gray-500 shrink-0">
                                    <x-heroicon-s-user class="w-4 h-4" />
                                </div>
                                <div class="min-w-0">
                                    <div class="font-bold text-sm text-gray-900 truncate">{{ $assignment->user->name }}</div>
                                    <div class="text-xs text-gray-500 truncate">{{ $assignment->user->email }}</div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="text-sm text-gray-500 mt-2">Belum ada user yang menempati jabatan ini.</p>
                @endif
            </x-filament::section>

            {{-- Sub Jabatan --}}
            @if($this->selectedJobDetail->children && $this->selectedJobDetail->children->count() > 0)
                <x-filament::section>
                    <x-slot name="heading">Sub Jabatan bawahan ({{ $this->selectedJobDetail->children->count() }})</x-slot>

                    <div class="space-y-3 mt-2">
                        @foreach($this->selectedJobDetail->children as $child)
                            <div class="flex items-center justify-between p-4 bg-white border border-gray-200 rounded-xl hover:border-primary-500 transition-colors">
                                <div class="flex items-start gap-3">
                                    <div class="mt-0.5">
                                        <div class="h-8 w-8 rounded-full bg-gray-100 flex items-center justify-center text-gray-500">
                                            <x-heroicon-s-user class="w-4 h-4" />
                                        </div>
                                    </div>
                                    <div>
                                        <div class="font-bold text-sm text-gray-900">{{ $child->name }}</div>
                                        @if($child->orgStructure)
                                            <div class="text-xs font-medium text-primary-600 mt-0.5">{{ $child->orgStructure->name }}</div>
                                        @endif
                                    </div>
                                </div>
                                <button
                                    type="button"
                                    wire:click="openJobDetail('{{ $child->id }}')"
                                    class="flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-gray-600 bg-white border border-gray-200 rounded-lg shadow-sm hover:border-primary-300 hover:text-primary-600 hover:bg-primary-50 transition-colors shrink-0"
                                >
                                    <x-heroicon-m-eye class="w-4 h-4" />
                                    Lihat
                                </button>
                            </div>
                        @endforeach
                    </div>
                </x-filament::section>
            @endif

            {{-- Action Buttons --}}
            <div class="flex justify-end gap-3 pt-4 border-t border-gray-200">
                <button
                    type="button"
                    x-on:click="open = false"
                    class="px-4 py-2 text-sm font-semibold text-gray-700 bg-white border border-gray-300 rounded-lg shadow-sm hover:bg-gray-50 transition-colors"
                >
                    Tutup
                </button>

                @can('edit_master_data_jabatan')
                    <button
                        type="button"
                        wire:click="editJob('{{ $this->selectedJobDetail->id }}')"
                        class="flex items-center gap-1.5 px-4 py-2 text-sm font-semibold text-white bg-primary-600 rounded-lg shadow-sm hover:bg-primary-700 transition-colors"
                    >
                        <x-heroicon-m-pencil-square class="w-4 h-4" />
                        Edit Jabatan
                    </button>
                @endcan
            </div>
        </div>
    @endif
        </div>
    </div>
</div>

@push('scripts')
    @filamentScripts
@endpush
</div>
