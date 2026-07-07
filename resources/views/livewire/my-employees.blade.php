<div>
@push('head')
    @filamentStyles
    {{-- @filamentStyles cuma register asset yang scoped ke Panel aktif; CSS utama Filament
         (fi-* classes, dipakai table/button/section) di-load manual karena halaman ini di luar Panel --}}
    <link rel="stylesheet" href="{{ asset('css/filament/filament/app.css') }}">
@endpush

<div class="mb-6 flex items-center justify-between">
    <div>
        <p class="text-xs text-slate-500 mb-1">{{ now()->translatedFormat('l, d F Y') }}</p>
        <h1 class="text-[22px] font-extrabold text-slate-900 tracking-tight">Karyawanku</h1>
    </div>
</div>

<div style="margin-bottom:20px;">
    <livewire:pending-approvals-card />
</div>

@if ($canBrowseAllCompanies)
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

{{ $this->table }}

@push('scripts')
    @filamentScripts
@endpush
</div>
