<div>
<x-page-header title="Kelola Counseling" subtitle="Keluhan/konsul karyawan company-wide" />

<div style="display:inline-flex;gap:2px;background:var(--surface-alt);padding:3px;border-radius:8px;margin-bottom:14px;flex-wrap:wrap;">
    <button type="button" wire:click="setStatus('')" style="padding:4px 12px;font-size:11.5px;font-weight:600;border:none;border-radius:6px;cursor:pointer;font-family:inherit;background:{{ $status === '' ? 'var(--surface)' : 'transparent' }};color:{{ $status === '' ? 'var(--text-heading)' : 'var(--text-muted)' }};box-shadow:{{ $status === '' ? 'var(--shadow-card)' : 'none' }};">Semua</button>
    @foreach ($this->statuses as $s)
        <button type="button" wire:click="setStatus('{{ $s->value }}')" style="padding:4px 12px;font-size:11.5px;font-weight:600;border:none;border-radius:6px;cursor:pointer;font-family:inherit;background:{{ $status === $s->value ? 'var(--surface)' : 'transparent' }};color:{{ $status === $s->value ? 'var(--text-heading)' : 'var(--text-muted)' }};box-shadow:{{ $status === $s->value ? 'var(--shadow-card)' : 'none' }};">{{ $s->getLabel() }}</button>
    @endforeach
</div>

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
            <input type="search" wire:model.live.debounce.300ms="search" class="form-input" placeholder="Nama karyawan / isi keluhan..." style="padding-left:38px;">
        </div>
    </div>
</div>

@php $counselings = $this->counselings; @endphp

<div class="pwa-list">
    @forelse ($counselings as $counseling)
        <a href="{{ route('counseling.kelola.show', $counseling) }}" wire:navigate class="pwa-list-row" style="text-decoration:none;">
            <div class="pwa-tile-icon neutral"><i class="fa-solid fa-comment-dots"></i></div>
            <div style="flex:1;min-width:0;">
                <p class="pwa-row-title">{{ $counseling->user?->name ?? '-' }}</p>
                <p style="font-size:11.5px;color:var(--text-muted);margin-top:2px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">{{ $counseling->issue }}</p>
            </div>
            <span class="badge {{ $counseling->status === 'done' ? 'badge-approved' : 'badge-pending' }}">
                {{ \App\Enum\CounselingStatus::tryFrom($counseling->status)?->getLabel() ?? $counseling->status }}
            </span>
        </a>
    @empty
        <div class="pwa-list-row" style="opacity:0.7;">
            <div style="flex:1;min-width:0;">
                <p class="pwa-row-title">Belum ada keluhan</p>
                <span style="font-size:11.5px;color:var(--text-muted);">Keluhan yang disubmit karyawan akan muncul di sini.</span>
            </div>
        </div>
    @endforelse
</div>

<div style="margin-top:16px;">
    {{ $counselings->links('livewire.partials.pagination') }}
</div>
</div>
