<div>
<x-page-header title="Pengajuan Absensi" />

{{-- AKSI CEPAT: reuse form pengajuan cuti/izin/lembur yang sudah ada, bukan bikin form baru --}}
<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-bottom:24px;">
    <a href="{{ route('my-attendance-requests.leave') }}" wire:navigate class="pwa-action-btn navy">
        <div class="pwa-action-btn-deco-1"></div><div class="pwa-action-btn-deco-2"></div>
        <div class="pwa-action-btn-content">
            <i class="fa-solid fa-umbrella-beach"></i>
            <span>Cuti</span>
        </div>
    </a>
    <a href="{{ route('my-attendance-requests.permission') }}" wire:navigate class="pwa-action-btn gold">
        <div class="pwa-action-btn-deco-1"></div><div class="pwa-action-btn-deco-2"></div>
        <div class="pwa-action-btn-content">
            <i class="fa-solid fa-clock"></i>
            <span>Izin</span>
        </div>
    </a>
    <a href="{{ route('my-attendance-requests.overtime') }}" wire:navigate class="pwa-action-btn green">
        <div class="pwa-action-btn-deco-1"></div><div class="pwa-action-btn-deco-2"></div>
        <div class="pwa-action-btn-content">
            <i class="fa-solid fa-briefcase-clock"></i>
            <span>Lembur</span>
        </div>
    </a>
</div>

{{-- Filter disembunyikan default, dibuka lewat tombol ikon. x-data lokal (bukan state Livewire) -
     wire:click pada pill filter me-render ulang komponen tapi Alpine mempertahankan $open. --}}
<div x-data="{ open: false }">
<div style="display:flex;justify-content:space-between;align-items:center;">
    <span class="pwa-section-title">Riwayat Pengajuan</span>
    <button type="button" @click="open = ! open" class="btn-ghost" style="padding:6px 10px;position:relative;" title="Filter" aria-label="Tampilkan/sembunyikan filter" :aria-expanded="open">
        <i class="fa-solid fa-filter"></i>
        @if ($type !== '' || $status !== '')
            <span style="position:absolute;top:3px;right:3px;width:7px;height:7px;border-radius:50%;background:var(--brand);"></span>
        @endif
    </button>
</div>

{{-- Filter Jenis + Status — pill button (wire:click), pola sama UserTransitionsPage. --}}
<div x-show="open" x-cloak style="display:flex;flex-wrap:wrap;gap:10px;margin:10px 0 14px;">
    <div style="display:inline-flex;gap:2px;background:var(--surface-alt);padding:3px;border-radius:8px;flex-wrap:wrap;">
        <button type="button" wire:click="setType('')" style="padding:4px 12px;font-size:11.5px;font-weight:600;border:none;border-radius:6px;cursor:pointer;font-family:inherit;background:{{ $type === '' ? 'var(--surface)' : 'transparent' }};color:{{ $type === '' ? 'var(--text-heading)' : 'var(--text-muted)' }};box-shadow:{{ $type === '' ? 'var(--shadow-card)' : 'none' }};">Semua Jenis</button>
        @foreach ($this->typeOptions as $value => $label)
            <button type="button" wire:click="setType('{{ $value }}')" style="padding:4px 12px;font-size:11.5px;font-weight:600;border:none;border-radius:6px;cursor:pointer;font-family:inherit;background:{{ $type === $value ? 'var(--surface)' : 'transparent' }};color:{{ $type === $value ? 'var(--text-heading)' : 'var(--text-muted)' }};box-shadow:{{ $type === $value ? 'var(--shadow-card)' : 'none' }};">{{ $label }}</button>
        @endforeach
    </div>

    <div style="display:inline-flex;gap:2px;background:var(--surface-alt);padding:3px;border-radius:8px;flex-wrap:wrap;">
        <button type="button" wire:click="setStatus('')" style="padding:4px 12px;font-size:11.5px;font-weight:600;border:none;border-radius:6px;cursor:pointer;font-family:inherit;background:{{ $status === '' ? 'var(--surface)' : 'transparent' }};color:{{ $status === '' ? 'var(--text-heading)' : 'var(--text-muted)' }};box-shadow:{{ $status === '' ? 'var(--shadow-card)' : 'none' }};">Semua Status</button>
        @foreach ($this->statusOptions as $s)
            <button type="button" wire:click="setStatus('{{ $s->value }}')" style="padding:4px 12px;font-size:11.5px;font-weight:600;border:none;border-radius:6px;cursor:pointer;font-family:inherit;background:{{ $status === $s->value ? 'var(--surface)' : 'transparent' }};color:{{ $status === $s->value ? 'var(--text-heading)' : 'var(--text-muted)' }};box-shadow:{{ $status === $s->value ? 'var(--shadow-card)' : 'none' }};">{{ $s->getLabel() }}</button>
        @endforeach
    </div>
</div>
</div>

@php $requests = $this->requests; @endphp

<!-- MOBILE: card -->
<div class="md:hidden">
    @if ($requests->isEmpty())
        <div class="pwa-list" style="padding:32px 16px;text-align:center;">
            <p style="font-size:13px;color:var(--text-muted);margin:0;">Belum ada pengajuan.</p>
        </div>
    @else
        <div class="pwa-list">
            @foreach ($requests as $record)
                <a href="{{ route('my-attendance-requests.show', ['record' => $record]) }}" wire:navigate class="pwa-list-row" style="text-decoration:none;">
                    <div class="pwa-tile-icon {{ $this->typeTile($record->type) }}"><i class="fa-solid {{ $this->typeIcon($record->type) }}"></i></div>
                    <div style="flex:1;min-width:0;">
                        <p style="font-size:13px;font-weight:600;color:var(--text-heading);">{{ $this->typeLabel($record->type) }} · {{ $this->keterangan($record) }}</p>
                        <p style="font-size:11px;color:var(--text-muted);margin-top:2px;">{{ $record->created_at?->translatedFormat('j F Y H:i') }}</p>
                        <span class="badge {{ $this->statusBadgeClass($record->status) }}" style="margin-top:4px;">{{ \App\Enum\AttendanceReqStatus::from($record->status)->getLabel() }}</span>
                    </div>
                    <i class="fa-solid fa-chevron-right"></i>
                </a>
            @endforeach
        </div>

        <div style="margin-top:16px;">
            {{ $requests->links('livewire.partials.pagination') }}
        </div>
    @endif
</div>

<!-- DESKTOP: tabel -->
<div class="hidden md:block">
    <div style="background:var(--surface);border-radius:12px;box-shadow:var(--shadow-card);overflow-x:auto;{{ $requests->isEmpty() ? 'display:none;' : '' }}">
        <table style="width:100%;border-collapse:collapse;font-size:12.5px;">
            <thead>
                <tr style="border-bottom:1.5px solid var(--border);">
                    <th style="text-align:left;padding:10px 14px;white-space:nowrap;">Tanggal Pengajuan</th>
                    <th style="text-align:left;padding:10px 14px;white-space:nowrap;">Jenis</th>
                    <th style="text-align:left;padding:10px 14px;">Keterangan</th>
                    <th style="text-align:center;padding:10px 14px;white-space:nowrap;">Status</th>
                    <th style="text-align:center;padding:10px 14px;white-space:nowrap;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($requests as $record)
                    <tr wire:key="'attreq-'.{{ $record->id }}" style="border-bottom:1px solid var(--border-soft);">
                        <td style="padding:9px 14px;white-space:nowrap;">{{ $record->created_at?->translatedFormat('j F Y H:i') }}</td>
                        <td style="padding:9px 14px;white-space:nowrap;">
                            <span class="badge" style="background:var(--surface-muted);color:var(--text-muted);">{{ $this->typeLabel($record->type) }}</span>
                        </td>
                        <td style="padding:9px 14px;">{{ $this->keterangan($record) }}</td>
                        <td style="padding:9px 14px;text-align:center;white-space:nowrap;">
                            <span class="badge {{ $this->statusBadgeClass($record->status) }}">{{ \App\Enum\AttendanceReqStatus::from($record->status)->getLabel() }}</span>
                        </td>
                        <td style="padding:9px 14px;text-align:center;white-space:nowrap;">
                            <a href="{{ route('my-attendance-requests.show', ['record' => $record]) }}" wire:navigate class="btn-ghost" style="padding:5px 10px;font-size:11px;" title="Detail"><i class="fa-solid fa-arrow-right"></i></a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @if ($requests->isEmpty())
        <div style="padding:32px 16px;text-align:center;">
            <p style="font-size:13px;color:var(--text-muted);margin:0;">Belum ada pengajuan.</p>
        </div>
    @endif

    <div style="margin-top:16px;">
        {{ $requests->links('livewire.partials.pagination') }}
    </div>
</div>

</div>
