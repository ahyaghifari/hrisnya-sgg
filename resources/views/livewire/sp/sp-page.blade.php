<div>
<x-page-header title="Surat Peringatan" subtitle="Kelola SP karyawan" />

@if ($this->canCreate)
    <div style="display:flex;justify-content:flex-end;margin-bottom:16px;">
        <a href="{{ route('ba.create') }}" wire:navigate class="btn-primary">
            <i class="fa-solid fa-plus"></i> Buat Surat Peringatan
        </a>
    </div>
@endif

<div class="mb-4" style="display:flex;flex-wrap:wrap;gap:12px;">
    @if ($this->canBrowseAllCompanies)
        <div style="min-width:220px;">
            <label class="form-label">Perusahaan</label>
            <select wire:model.live="companyId" class="form-input">
                <option value="">Semua perusahaan</option>
                @foreach ($this->companyOptions as $id => $name)
                    <option value="{{ $id }}">{{ $name }}</option>
                @endforeach
            </select>
        </div>
    @endif

    <div style="min-width:220px;flex:1;">
        <label class="form-label">Cari</label>
        <div style="position:relative;">
            <i class="fa-solid fa-magnifying-glass" style="position:absolute;left:14px;top:50%;transform:translateY(-50%);color:var(--text-faint);font-size:13px;"></i>
            <input type="search" wire:model.live.debounce.300ms="search" class="form-input" placeholder="Nama karyawan..." style="padding-left:38px;">
        </div>
    </div>
</div>

@php $cases = $this->cases; @endphp

{{-- Berita Acara, Keputusan, dan Surat Peringatan dalam SATU daftar; tahapnya tampil di tiap baris. --}}
<div class="pwa-list">
    @forelse ($cases as $case)
        @include('livewire.sp.partials.case-row', ['case' => $case, 'mine' => false])
    @empty
        <div class="pwa-list-row" style="opacity:0.7;">
            <div style="flex:1;min-width:0;">
                <p class="pwa-row-title">Belum ada data</p>
                <span style="font-size:11.5px;color:var(--text-muted);">Berita Acara dan Surat Peringatan akan muncul di sini.</span>
            </div>
        </div>
    @endforelse
</div>

<div style="margin-top:16px;">
    {{ $cases->links('livewire.partials.pagination') }}
</div>
</div>
