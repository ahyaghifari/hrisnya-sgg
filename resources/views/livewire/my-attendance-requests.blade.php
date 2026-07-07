<div>
@push('head')
    @filamentStyles
    <link rel="stylesheet" href="{{ asset('css/filament/filament/app.css') }}">
@endpush

<div class="mb-6 flex items-center justify-between">
    <div>
        <p class="text-xs text-slate-500 mb-1">{{ now()->translatedFormat('l, d F Y') }}</p>
        <h1 class="text-[22px] font-extrabold text-slate-900 tracking-tight">Riwayat Pengajuan</h1>
    </div>
</div>

{{ $this->table }}

@push('scripts')
    @filamentScripts
@endpush
</div>

