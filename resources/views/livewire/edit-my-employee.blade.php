<div>
@push('head')
    @filamentStyles
    <link rel="stylesheet" href="{{ asset('css/filament/filament/app.css') }}">
@endpush

<x-page-header title="Edit Karyawan" :back="route('my-staff')" />

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
