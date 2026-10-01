<div>
<x-page-header title="Berita Acara" subtitle="Proses klarifikasi sebelum Surat Peringatan" />

@if ($this->canCreate)
    <div style="display:flex;justify-content:flex-end;margin-bottom:16px;">
        <a href="{{ route('ba.create') }}" wire:navigate class="btn-primary">
            <i class="fa-solid fa-plus"></i> Buat Berita Acara
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

@php $reports = $this->reports; @endphp

<div class="pwa-list">
    @forelse ($reports as $report)
        <a href="{{ route('ba.show', $report) }}" wire:navigate class="pwa-list-row" style="text-decoration:none;">
            <div style="flex:1;min-width:0;">
                <p class="pwa-row-title">{{ $report->user?->name ?? '-' }}</p>
                <span style="font-size:11.5px;color:var(--text-muted);">
                    {{ $report->status->getLabel() }}
                    @if ($report->keputusan)
                        &middot; {{ $report->keputusan->getLabel() }}
                    @endif
                    &middot; {{ $report->created_at->translatedFormat('d M Y') }}
                </span>
            </div>
            <i class="fa-solid fa-chevron-right"></i>
        </a>
    @empty
        <div class="pwa-list-row" style="opacity:0.7;">
            <div style="flex:1;min-width:0;">
                <p class="pwa-row-title">Belum ada Berita Acara</p>
                <span style="font-size:11.5px;color:var(--text-muted);">Berita Acara yang dibuat akan muncul di sini.</span>
            </div>
        </div>
    @endforelse
</div>

<div style="margin-top:16px;">
    {{ $reports->links('livewire.partials.pagination') }}
</div>
</div>
