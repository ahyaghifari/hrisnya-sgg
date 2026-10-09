<div>
<x-page-header title="Coaching" subtitle="Kelola sesi coaching/pembinaan karyawan" />

@if ($this->canCreate)
    <div style="display:flex;justify-content:flex-end;gap:8px;flex-wrap:wrap;margin-bottom:16px;">
        <a href="{{ route('coaching.undangan.create') }}" wire:navigate class="btn-secondary">
            <i class="fa-solid fa-envelope-open-text"></i> Buat Undangan
        </a>
        <a href="{{ route('coaching.create') }}" wire:navigate class="btn-primary">
            <i class="fa-solid fa-plus"></i> Buat Coaching
        </a>
    </div>

    @if ($this->upcomingInvitations->isNotEmpty())
        <div class="pwa-list" style="margin-bottom:16px;">
            @foreach ($this->upcomingInvitations as $invitation)
                <a href="{{ route('coaching.undangan.show', $invitation) }}" wire:navigate class="pwa-list-row" style="text-decoration:none;">
                    <div style="flex:1;min-width:0;">
                        <p class="pwa-row-title"><i class="fa-solid fa-envelope-open-text" style="font-size:12px;margin-right:6px;color:var(--brand);"></i>Undangan: {{ $invitation->user?->name }}</p>
                        <span style="font-size:11.5px;color:var(--text-muted);">{{ $invitation->scheduled_at->translatedFormat('l, d M Y') }} · {{ $invitation->scheduled_at->format('H:i') }} · {{ $invitation->location }}</span>
                    </div>
                    <i class="fa-solid fa-chevron-right"></i>
                </a>
            @endforeach
        </div>
    @endif
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

@php $coachings = $this->coachings; @endphp

<div class="pwa-list">
    @forelse ($coachings as $coaching)
        <a href="{{ route('coaching.show', $coaching) }}" wire:navigate class="pwa-list-row" style="text-decoration:none;">
            <div style="flex:1;min-width:0;">
                <p class="pwa-row-title">{{ $coaching->user?->name ?? '-' }}</p>
                <span style="font-size:11.5px;color:var(--text-muted);">
                    {{ $coaching->title }}
                    &middot; Coach: {{ $coaching->coachBy?->name ?? '-' }}
                </span>
            </div>
            <i class="fa-solid fa-chevron-right"></i>
        </a>
    @empty
        <div class="pwa-list-row" style="opacity:0.7;">
            <div style="flex:1;min-width:0;">
                <p class="pwa-row-title">Belum ada coaching</p>
                <span style="font-size:11.5px;color:var(--text-muted);">Coaching yang dibuat akan muncul di sini.</span>
            </div>
        </div>
    @endforelse
</div>

<div style="margin-top:16px;">
    {{ $coachings->links('livewire.partials.pagination') }}
</div>
</div>
