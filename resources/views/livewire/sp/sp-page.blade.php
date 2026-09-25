<div>
<x-page-header title="Surat Peringatan" subtitle="Kelola SP karyawan" />

@if ($this->canCreate)
    <div style="display:flex;justify-content:flex-end;margin-bottom:16px;">
        <a href="{{ route('sp.create') }}" wire:navigate class="btn-primary">
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

@php
    $sps = $this->sps;
    $levelColors = [
        'sp1' => ['bg' => '#fef9c3', 'fg' => '#a16207'],
        'sp2' => ['bg' => '#ffedd5', 'fg' => '#c2410c'],
        'sp3' => ['bg' => '#fee2e2', 'fg' => '#b91c1c'],
    ];
@endphp

<div class="pwa-list">
    @forelse ($sps as $sp)
        @php
            $isActive = $sp->start_date->lte(today()) && ($sp->end_date === null || $sp->end_date->gte(today()));
            $levelColor = $levelColors[$sp->sp_level->value] ?? ['bg' => '#f1f5f9', 'fg' => '#64748b'];
        @endphp
        <a href="{{ route('sp.show', $sp) }}" wire:navigate class="pwa-list-row" style="text-decoration:none;">
            <div style="flex:1;min-width:0;">
                <p class="pwa-row-title">
                    <span class="badge" style="background:{{ $levelColor['bg'] }};color:{{ $levelColor['fg'] }};margin-right:6px;">{{ $sp->sp_level->getLabel() }}</span>
                    {{ $sp->user?->name ?? '-' }}
                </p>
                <span style="font-size:11.5px;color:var(--text-muted);">
                    @if ($sp->nomor)
                        No. {{ $sp->nomor }} &middot;
                    @endif
                    {{ $sp->start_date->translatedFormat('d M Y') }}
                    &middot; {{ $isActive ? 'Aktif' : 'Expired' }}
                </span>
            </div>
            <i class="fa-solid fa-chevron-right"></i>
        </a>
    @empty
        <div class="pwa-list-row" style="opacity:0.7;">
            <div style="flex:1;min-width:0;">
                <p class="pwa-row-title">Belum ada SP</p>
                <span style="font-size:11.5px;color:var(--text-muted);">SP yang diterbitkan akan muncul di sini.</span>
            </div>
        </div>
    @endforelse
</div>

<div style="margin-top:16px;">
    {{ $sps->links('livewire.partials.pagination') }}
</div>
</div>
