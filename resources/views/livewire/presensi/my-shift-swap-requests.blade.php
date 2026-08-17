<div>
<x-page-header title="Tukar Shift" subtitle="Riwayat pengajuan tukar shift Anda" />

<div style="display:flex;flex-wrap:wrap;gap:10px;margin:10px 0 14px;">
    <div style="display:inline-flex;gap:2px;background:var(--surface-alt);padding:3px;border-radius:8px;flex-wrap:wrap;">
        <button type="button" wire:click="setStatus('')" style="padding:4px 12px;font-size:11.5px;font-weight:600;border:none;border-radius:6px;cursor:pointer;font-family:inherit;background:{{ $status === '' ? 'var(--surface)' : 'transparent' }};color:{{ $status === '' ? 'var(--text-heading)' : 'var(--text-muted)' }};box-shadow:{{ $status === '' ? 'var(--shadow-card)' : 'none' }};">Semua Status</button>
        @foreach ($this->statusOptions as $value => $label)
            <button type="button" wire:click="setStatus('{{ $value }}')" style="padding:4px 12px;font-size:11.5px;font-weight:600;border:none;border-radius:6px;cursor:pointer;font-family:inherit;background:{{ $status === $value ? 'var(--surface)' : 'transparent' }};color:{{ $status === $value ? 'var(--text-heading)' : 'var(--text-muted)' }};box-shadow:{{ $status === $value ? 'var(--shadow-card)' : 'none' }};">{{ $label }}</button>
        @endforeach
    </div>
</div>

@php $requests = $this->requests; @endphp

<!-- MOBILE: card -->
<div class="md:hidden">
    @if ($requests->isEmpty())
        <div class="pwa-list" style="padding:32px 16px;text-align:center;">
            <p style="font-size:13px;color:var(--text-muted);margin:0;">Belum ada pengajuan tukar shift.</p>
        </div>
    @else
        <div class="pwa-list">
            @foreach ($requests as $record)
                <a href="{{ route('tukar-shift.show', ['record' => $record]) }}" wire:navigate class="pwa-list-row" style="text-decoration:none;">
                    <div class="pwa-tile-icon neutral"><i class="fa-solid fa-right-left"></i></div>
                    <div style="flex:1;min-width:0;">
                        <p style="font-size:13px;font-weight:600;color:var(--text-heading);">{{ $this->keterangan($record) }}</p>
                        <p style="font-size:11px;color:var(--text-muted);margin-top:2px;">{{ $record->created_at?->translatedFormat('j F Y H:i') }}</p>
                        <span class="badge {{ $this->statusBadgeClass($record->status) }}" style="margin-top:4px;">{{ $this->statusLabel($record->status) }}</span>
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
                    <th style="text-align:left;padding:10px 14px;">Keterangan</th>
                    <th style="text-align:center;padding:10px 14px;white-space:nowrap;">Status</th>
                    <th style="text-align:center;padding:10px 14px;white-space:nowrap;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($requests as $record)
                    <tr wire:key="'swapreq-'.{{ $record->id }}" style="border-bottom:1px solid var(--border-soft);">
                        <td style="padding:9px 14px;white-space:nowrap;">{{ $record->created_at?->translatedFormat('j F Y H:i') }}</td>
                        <td style="padding:9px 14px;">{{ $this->keterangan($record) }}</td>
                        <td style="padding:9px 14px;text-align:center;white-space:nowrap;">
                            <span class="badge {{ $this->statusBadgeClass($record->status) }}">{{ $this->statusLabel($record->status) }}</span>
                        </td>
                        <td style="padding:9px 14px;text-align:center;white-space:nowrap;">
                            <a href="{{ route('tukar-shift.show', ['record' => $record]) }}" wire:navigate class="btn-ghost" style="padding:5px 10px;font-size:11px;" title="Detail"><i class="fa-solid fa-arrow-right"></i></a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @if ($requests->isEmpty())
        <div style="padding:32px 16px;text-align:center;">
            <p style="font-size:13px;color:var(--text-muted);margin:0;">Belum ada pengajuan tukar shift.</p>
        </div>
    @endif

    <div style="margin-top:16px;">
        {{ $requests->links('livewire.partials.pagination') }}
    </div>
</div>

</div>
