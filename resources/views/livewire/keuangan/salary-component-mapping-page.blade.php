<div class="p-4 md:p-6">
    <x-page-header title="Mapping Komponen Gaji" />

    @if ($this->canBrowseAllCompanies())
        <div class="mb-4 max-w-sm">
            <label class="block text-xs font-semibold text-slate-600 mb-1">Perusahaan</label>
            <select wire:model.live="companyId" class="w-full rounded-lg border-slate-300 text-sm">
                <option value="">— Pilih Perusahaan —</option>
                @foreach ($this->companyOptions as $id => $name)
                    <option value="{{ $id }}">{{ $name }}</option>
                @endforeach
            </select>
        </div>
    @endif

    <div class="mt-4">
        {{ $this->table }}
    </div>
</div>
