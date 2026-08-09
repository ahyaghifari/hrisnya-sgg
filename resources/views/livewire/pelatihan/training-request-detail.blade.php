<div>
<x-page-header title="Detail Pengajuan Pelatihan" :back="route('pelatihan.pengajuan-saya')" />

<x-approval-timeline :timeline="$this->timeline" />

@php
    $statusBadge = match ($record->status) {
        'approved' => 'badge-approved',
        'in_review', 'pending' => 'badge-pending',
        'rejected' => 'badge-rejected',
        default => 'badge-cancelled',
    };
    $statusLabel = match ($record->status) {
        'approved' => 'Disetujui',
        'in_review', 'pending' => 'Menunggu Persetujuan',
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

    $formatDate = fn (?\Illuminate\Support\Carbon $date) => $date?->translatedFormat('d F Y');
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
            </div>
        @endif
    </div>
@endif

<div class="grid grid-cols-1 md:grid-cols-2" style="gap:16px;align-items:start;">
    <div style="background:var(--surface);border-radius:16px;box-shadow:var(--shadow-card);padding:18px;">
        {!! $sectionHeader('fa-user', 'Pemohon') !!}
        {!! $renderField('Nama Karyawan', $record->user->name) !!}
        {!! $renderField('NIP', $record->user->nip, false) !!}
    </div>

    <div style="background:var(--surface);border-radius:16px;box-shadow:var(--shadow-card);padding:18px;">
        {!! $sectionHeader('fa-graduation-cap', 'Detail Pelatihan') !!}
        {!! $renderField('Judul Pelatihan', $record->title) !!}
        {!! $renderField('Penyelenggara', $record->organizer) !!}
        {!! $renderField('Tanggal Mulai', $formatDate($record->start_date)) !!}
        {!! $renderField('Tanggal Selesai', $formatDate($record->end_date)) !!}
        {!! $renderField('Estimasi Biaya', $record->estimated_cost ? 'Rp ' . number_format($record->estimated_cost, 0, ',', '.') : null) !!}
        {!! $renderField('Lama Ikatan Dinas', $record->ikatan_dinas_tahun ? rtrim(rtrim(number_format($record->ikatan_dinas_tahun, 2, ',', '.'), '0'), ',') . ' Tahun' : null) !!}
        {!! $renderField('Selesai Ikatan Dinas', $record->ikatan_dinas_selesai?->translatedFormat('d F Y')) !!}
        <div style="display:flex;align-items:center;justify-content:space-between;gap:16px;padding:10px 0;">
            <span style="font-size:12.5px;color:var(--text-muted);">Status</span>
            <span class="badge {{ $statusBadge }}">{{ $statusLabel }}</span>
        </div>
    </div>

    @if ($record->description)
        <div style="background:var(--surface);border-radius:16px;box-shadow:var(--shadow-card);padding:18px;grid-column:1 / -1;">
            {!! $sectionHeader('fa-align-left', 'Deskripsi/Alasan') !!}
            <p style="font-size:13px;color:var(--text-body);margin:8px 0 0;white-space:pre-line;">{{ $record->description }}</p>
        </div>
    @endif

    <x-approval-history-list :histories="$record->approvalHistories" style="grid-column:1 / -1;" />
</div>
</div>
