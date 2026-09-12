<div>
<x-page-header title="Detail Tukar Shift" :back="route('tukar-shift')" />

<x-approval-timeline :timeline="$this->timeline" />

@if ($this->conflictWarnings)
    <div style="background:var(--warning-soft-bg,rgba(217,119,6,0.1));border:1px solid var(--warning-soft-border,rgba(217,119,6,0.3));border-radius:12px;padding:12px 16px;margin-bottom:16px;">
        @foreach ($this->conflictWarnings as $warning)
            <p style="font-size:12.5px;color:var(--warning-soft-text,#b45309);margin:0;{{ !$loop->last ? 'margin-bottom:6px;' : '' }}">
                <i class="fa-solid fa-triangle-exclamation"></i> {{ $warning }}
            </p>
        @endforeach
    </div>
@endif

@php
    $statusBadge = match ($record->status) {
        'approved' => 'badge-approved',
        'in_review', 'pending' => 'badge-pending',
        'rejected' => 'badge-rejected',
        default => 'badge-cancelled',
    };
    $statusLabel = match ($record->status) {
        'in_review', 'pending' => 'Diproses',
        'approved' => 'Disetujui',
        'rejected' => 'Ditolak',
        default => $record->status,
    };

    $renderField = function (string $label, ?string $value, bool $showBorder = true) {
        $borderStyle = $showBorder ? 'border-bottom:1px solid var(--border-soft);' : '';
        $valueHtml = $value
            ? '<span style="font-size:13.5px;font-weight:600;color:var(--text-body);text-align:right;">' . e($value) . '</span>'
            : '<span style="font-size:12.5px;font-style:italic;color:var(--text-muted);text-align:right;">-</span>';

        return '<div style="display:flex;align-items:flex-start;justify-content:space-between;gap:16px;padding:10px 0;' . $borderStyle . '">'
            . '<span style="font-size:12.5px;color:var(--text-muted);flex-shrink:0;">' . e($label) . '</span>'
            . $valueHtml
            . '</div>';
    };

    $sectionHeader = function (string $icon, string $title) {
        return '<div style="display:flex;align-items:center;gap:10px;margin-bottom:6px;">'
            . '<div style="flex-shrink:0;width:32px;height:32px;border-radius:9px;background:rgba(4,44,108,0.08);display:flex;align-items:center;justify-content:center;">'
            . '<i class="fa-solid ' . $icon . '" style="color:var(--brand);font-size:13px;"></i>'
            . '</div>'
            . '<h4 style="font-size:13.5px;font-weight:700;color:var(--text-heading);margin:0;">' . e($title) . '</h4>'
            . '</div>';
    };
@endphp

@if ($this->canApprove)
    <div style="background:var(--surface);border-radius:16px;box-shadow:var(--shadow-card);padding:18px;margin-bottom:16px;">
        <h3 style="font-size:13.5px;font-weight:700;color:var(--text-heading);margin:0 0 14px;">Aksi Persetujuan</h3>

        @if ($pendingAction)
            <div style="display:flex;flex-direction:column;gap:12px;">
                <label class="form-label">Catatan <span style="color:var(--danger-text);">*</span></label>
                <textarea class="form-input" rows="3" placeholder="Tambahkan catatan..." wire:model="note"></textarea>
                @error('note') <p style="color:var(--danger-text);font-size:12px;">{{ $message }}</p> @enderror

                <div style="display:flex;gap:10px;">
                    <button type="button" wire:click="confirmAction" class="btn-primary">Konfirmasi</button>
                    <button type="button" wire:click="cancelChoice" class="btn-ghost">Batal</button>
                </div>
            </div>
        @else
            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                <button type="button" wire:click="chooseAction('approve')" class="btn-primary">
                    <i class="fa-solid fa-check"></i> Setujui
                </button>
                <button type="button" wire:click="chooseAction('reject')" class="btn-ghost" style="color:var(--danger-text);border-color:var(--danger-soft-border);">
                    <i class="fa-solid fa-xmark"></i> Tolak
                </button>
                <button type="button" wire:click="chooseAction('cancel')" class="btn-ghost">
                    <i class="fa-solid fa-ban"></i> Batalkan
                </button>
            </div>
        @endif
    </div>
@endif

<div class="grid grid-cols-1 md:grid-cols-2" style="gap:16px;align-items:start;">
    <div style="background:var(--surface);border-radius:16px;box-shadow:var(--shadow-card);padding:18px;">
        {!! $sectionHeader('fa-user', 'Pemohon') !!}
        {!! $renderField('Nama', $record->requester?->name) !!}
        {!! $renderField('NIP', $record->requester?->nip) !!}
        {!! $renderField('Jadwal yang Mau Ditukar', $record->reqSchedule?->shiftType?->name . ' — ' . $record->req_date->translatedFormat('d M Y'), false) !!}
    </div>

    <div style="background:var(--surface);border-radius:16px;box-shadow:var(--shadow-card);padding:18px;">
        {!! $sectionHeader('fa-right-left', 'Informasi Tukar Shift') !!}
        <div style="display:flex;align-items:center;justify-content:space-between;gap:16px;padding:10px 0;border-bottom:1px solid var(--border-soft);">
            <span style="font-size:12.5px;color:var(--text-muted);">Status</span>
            <span class="badge {{ $statusBadge }}">{{ $statusLabel }}</span>
        </div>
        {!! $renderField('Rekan Tujuan', $record->target?->name) !!}
        @if ($record->targetSchedule)
            {!! $renderField('Jadwal Rekan', $record->targetSchedule->shiftType?->name . ' — ' . $record->target_date->translatedFormat('d M Y')) !!}
        @else
            {!! $renderField('Mode', 'Serahkan shift ke rekan yang libur') !!}
        @endif
        {!! $renderField('Tanggapan Rekan', $this->targetResponseLabel) !!}
        {!! $renderField('Catatan', $record->notes, false) !!}
    </div>

    <x-approval-history-list :histories="$record->approvalHistories" />
</div>
</div>
