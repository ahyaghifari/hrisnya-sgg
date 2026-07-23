<div>
<x-page-header title="Detail Permintaan Tenaga Kerja" :back="route('rekrutmen.permintaan-tenaga-kerja')" />

<x-approval-timeline :timeline="$this->timeline" />

@php
    $status = \App\Enum\JobPositionReqStatus::tryFrom($record->status);
    $statusBadge = match ($status) {
        \App\Enum\JobPositionReqStatus::APPROVED => 'badge-approved',
        \App\Enum\JobPositionReqStatus::REJECTED => 'badge-rejected',
        default => 'badge-pending',
    };
@endphp

@if ($this->canApprove)
    <div style="background:var(--surface);border-radius:16px;box-shadow:var(--shadow-card);padding:18px;margin-bottom:16px;">
        <h3 style="font-size:13.5px;font-weight:700;color:var(--text-heading);margin:0 0 14px;">Aksi Persetujuan</h3>
        <div style="margin-bottom:12px;">
            <label class="form-label">Catatan</label>
            <textarea wire:model="note" class="form-input" rows="2" placeholder="Catatan persetujuan (opsional)..."></textarea>
        </div>
        <div style="display:flex;gap:10px;flex-wrap:wrap;">
            <button type="button" wire:click="approve" wire:confirm="Setujui permintaan tenaga kerja ini?" class="btn-primary">
                <i class="fa-solid fa-check"></i> Setujui
            </button>
            <button type="button" wire:click="reject" wire:confirm="Tolak permintaan ini?" class="btn-ghost" style="color:var(--danger-text);border-color:var(--danger-soft-border);">
                <i class="fa-solid fa-xmark"></i> Tolak
            </button>
        </div>
    </div>
@endif

<div class="grid grid-cols-1 md:grid-cols-2" style="gap:16px;align-items:start;">
    <div style="background:var(--surface);border-radius:16px;box-shadow:var(--shadow-card);padding:18px;">
        <div style="display:flex;align-items:center;justify-content:space-between;gap:16px;padding:10px 0;border-bottom:1px solid var(--border-soft);">
            <span style="font-size:12.5px;color:var(--text-muted);">Diajukan Oleh</span>
            <span style="font-size:13.5px;font-weight:600;color:var(--text-body);">{{ $record->user?->name ?? '-' }}</span>
        </div>
        <div style="display:flex;align-items:center;justify-content:space-between;gap:16px;padding:10px 0;border-bottom:1px solid var(--border-soft);">
            <span style="font-size:12.5px;color:var(--text-muted);">Perusahaan</span>
            <span style="font-size:13.5px;font-weight:600;color:var(--text-body);">{{ $record->company?->name ?? '-' }}</span>
        </div>
        <div style="display:flex;align-items:center;justify-content:space-between;gap:16px;padding:10px 0;border-bottom:1px solid var(--border-soft);">
            <span style="font-size:12.5px;color:var(--text-muted);">Jabatan</span>
            <span style="font-size:13.5px;font-weight:600;color:var(--text-body);">{{ $record->jobPosition?->name ?? '-' }}</span>
        </div>
        <div style="display:flex;align-items:center;justify-content:space-between;gap:16px;padding:10px 0;border-bottom:1px solid var(--border-soft);">
            <span style="font-size:12.5px;color:var(--text-muted);">Tipe</span>
            <span style="font-size:13.5px;font-weight:600;color:var(--text-body);">{{ \App\Enum\ManpowerRequestType::from($record->type)->getLabel() }}</span>
        </div>
        <div style="display:flex;align-items:center;justify-content:space-between;gap:16px;padding:10px 0;border-bottom:1px solid var(--border-soft);">
            <span style="font-size:12.5px;color:var(--text-muted);">Jumlah</span>
            <span style="font-size:13.5px;font-weight:600;color:var(--text-body);">{{ $record->quantity }}</span>
        </div>
        <div style="display:flex;align-items:center;justify-content:space-between;gap:16px;padding:10px 0;">
            <span style="font-size:12.5px;color:var(--text-muted);">Status</span>
            <span class="badge {{ $statusBadge }}">{{ $status?->label() ?? $record->status }}</span>
        </div>
    </div>

    @if ($record->notes || $record->file_path)
        <div style="background:var(--surface);border-radius:16px;box-shadow:var(--shadow-card);padding:18px;">
            @if ($record->notes)
                <div style="padding:10px 0;border-bottom:1px solid var(--border-soft);">
                    <span style="font-size:12.5px;color:var(--text-muted);display:block;margin-bottom:6px;">Catatan</span>
                    <p style="font-size:13px;color:var(--text-body);margin:0;white-space:pre-line;">{{ $record->notes }}</p>
                </div>
            @endif
            @if ($record->file_path)
                <div style="padding:10px 0;">
                    <span style="font-size:12.5px;color:var(--text-muted);display:block;margin-bottom:6px;">Lampiran</span>
                    <a href="{{ asset('storage/' . $record->file_path) }}" target="_blank" style="font-size:12.5px;color:var(--brand);text-decoration:none;display:flex;align-items:center;gap:6px;">
                        <i class="fa-solid fa-link"></i> {{ basename($record->file_path) }}
                    </a>
                </div>
            @endif
        </div>
    @endif
</div>
</div>
