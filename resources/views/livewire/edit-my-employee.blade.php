<div>
@push('head')
    @filamentStyles
    <link rel="stylesheet" href="{{ asset('css/filament/filament/app.css') }}">
@endpush

<div class="mb-6 flex items-center justify-between">
    <div>
        <p class="text-xs text-slate-500 mb-1">{{ now()->translatedFormat('l, d F Y') }}</p>
        <h1 class="text-[22px] font-extrabold text-slate-900 tracking-tight">Edit Karyawan</h1>
    </div>
    <a href="{{ route('my-employees') }}" wire:navigate class="text-sm font-semibold text-gray-600 hover:text-gray-900">
        &larr; Kembali
    </a>
</div>

<form wire:submit="save">
    <div class="bg-white rounded-xl border border-gray-200 p-6">
        {{ $this->getSchema('editForm') }}
    </div>

    <div class="flex justify-end mt-6">
        <button type="submit" class="px-4 py-2 text-sm font-semibold text-white bg-primary-600 rounded-lg shadow-sm hover:bg-primary-700 transition-colors">
            Simpan Perubahan
        </button>
    </div>
</form>

@push('scripts')
    @filamentScripts
@endpush
</div>
