<div>
<x-page-header title="Detail Pengajuan" :back="route('my-attendance-requests')" />

<x-approval-timeline :timeline="$this->timeline" />

@if ($this->canApprove)
    <div style="background:#fff;border-radius:16px;box-shadow:0 1px 4px rgba(0,0,0,0.06);padding:18px;margin-bottom:16px;">
        <h3 style="font-size:13.5px;font-weight:700;color:#1e293b;margin:0 0 14px;">Aksi Persetujuan</h3>

        @if ($pendingAction)
            <div style="display:flex;flex-direction:column;gap:12px;">
                <label class="form-label">Catatan <span style="color:#ef4444;">*</span></label>
                <textarea class="form-input" rows="3" placeholder="Tambahkan catatan..." wire:model="note"></textarea>
                @error('note') <p style="color:#ef4444;font-size:12px;">{{ $message }}</p> @enderror

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
                <button type="button" wire:click="chooseAction('reject')" class="btn-ghost" style="color:#ef4444;border-color:#fecaca;">
                    <i class="fa-solid fa-xmark"></i> Tolak
                </button>
                <button type="button" wire:click="chooseAction('cancel')" class="btn-ghost">
                    <i class="fa-solid fa-ban"></i> Batalkan
                </button>
            </div>
        @endif
    </div>
@endif

@php
    $profile = $record->user->profile;

    $typeLabel = ['overtime' => 'Lembur', 'leave' => 'Cuti', 'permission' => 'Izin'][$record->type] ?? $record->type;
    $statusBadge = match ($record->status) {
        'approved' => 'badge-approved',
        'in_review' => 'badge-pending',
        'rejected', 'failed' => 'badge-rejected',
        default => 'badge-cancelled',
    };
    $statusLabel = \App\Enum\AttendanceReqStatus::from($record->status)->getLabel();

    $renderField = function (string $label, ?string $value, bool $showBorder = true) {
        $borderStyle = $showBorder ? 'border-bottom:1px solid #f1f5f9;' : '';
        $valueHtml = $value
            ? '<span style="font-size:13.5px;font-weight:600;color:#334155;text-align:right;">' . e($value) . '</span>'
            : '<span style="font-size:12.5px;font-style:italic;color:#64748b;text-align:right;">-</span>';

        return '<div style="display:flex;align-items:flex-start;justify-content:space-between;gap:16px;padding:10px 0;' . $borderStyle . '">'
            . '<span style="font-size:12.5px;color:#64748b;flex-shrink:0;">' . e($label) . '</span>'
            . $valueHtml
            . '</div>';
    };

    $sectionHeader = function (string $icon, string $title) {
        return '<div style="display:flex;align-items:center;gap:10px;margin-bottom:6px;">'
            . '<div style="flex-shrink:0;width:32px;height:32px;border-radius:9px;background:rgba(4,44,108,0.08);display:flex;align-items:center;justify-content:center;">'
            . '<i class="fa-solid ' . $icon . '" style="color:#042c6c;font-size:13px;"></i>'
            . '</div>'
            . '<h4 style="font-size:13.5px;font-weight:700;color:#1e293b;margin:0;">' . e($title) . '</h4>'
            . '</div>';
    };
@endphp

<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:16px;align-items:start;">
    <div style="background:#fff;border-radius:16px;box-shadow:0 1px 4px rgba(0,0,0,0.06);padding:18px;">
        {!! $sectionHeader('fa-user', 'Informasi Karyawan') !!}
        {!! $renderField('Nama Karyawan', $record->user->name) !!}
        {!! $renderField('NIP', $record->user->nip) !!}
        {!! $renderField('Departemen', $profile?->department?->name) !!}
        {!! $renderField('Tipe Karyawan', $profile?->employeeType?->name, false) !!}
    </div>

    <div style="background:#fff;border-radius:16px;box-shadow:0 1px 4px rgba(0,0,0,0.06);padding:18px;">
        {!! $sectionHeader('fa-file-lines', 'Informasi Pengajuan') !!}
        {!! $renderField('Tipe Request', $typeLabel) !!}
        <div style="display:flex;align-items:center;justify-content:space-between;gap:16px;padding:10px 0;">
            <span style="font-size:12.5px;color:#64748b;">Status</span>
            <span class="badge {{ $statusBadge }}">{{ $statusLabel }}</span>
        </div>
    </div>

    @php
        $formatDate = fn (?string $date) => $date ? \Illuminate\Support\Carbon::parse($date)->translatedFormat('d F Y') : null;
    @endphp

    @if ($record->type === 'leave' && $record->leaveDetails->isNotEmpty())
        @php $leave = $record->leaveDetails->first(); @endphp
        <div style="background:#fff;border-radius:16px;box-shadow:0 1px 4px rgba(0,0,0,0.06);padding:18px;">
            {!! $sectionHeader('fa-umbrella-beach', 'Detail Cuti') !!}
            {!! $renderField('Tipe Cuti', $leave->leave_type) !!}
            {!! $renderField('Tanggal Mulai', $formatDate($leave->start_date)) !!}
            {!! $renderField('Tanggal Selesai', $formatDate($leave->end_date)) !!}
            {!! $renderField('Total Hari', $leave->total_days ? "{$leave->total_days} hari" : null, false) !!}
        </div>
    @endif

    @if ($record->type === 'permission' && $record->permissionDetails->isNotEmpty())
        @php $permission = $record->permissionDetails->first(); @endphp
        <div style="background:#fff;border-radius:16px;box-shadow:0 1px 4px rgba(0,0,0,0.06);padding:18px;">
            {!! $sectionHeader('fa-clock', 'Detail Izin') !!}
            {!! $renderField('Tipe Izin', $permission->permission_type) !!}
            {!! $renderField('Tanggal', $formatDate($permission->date)) !!}
            {!! $renderField('Waktu Mulai', $permission->start_time) !!}
            {!! $renderField('Waktu Selesai', $permission->end_time) !!}
            {!! $renderField('Lokasi', $permission->location) !!}
            {!! $renderField('Tujuan', $permission->purpose) !!}
            {!! $renderField('Nama Pengganti', $permission->substitute_user_name) !!}
            {!! $renderField('Tugas Pengganti', $permission->substitute_task, false) !!}
        </div>
    @endif

    @if ($record->type === 'overtime' && $record->overtimeDetails->isNotEmpty())
        <div style="background:#fff;border-radius:16px;box-shadow:0 1px 4px rgba(0,0,0,0.06);padding:18px;">
            {!! $sectionHeader('fa-briefcase-clock', 'Detail Lembur') !!}
            @foreach ($record->overtimeDetails as $overtime)
                {!! $renderField('Tipe Lembur', $overtime->overtime_type) !!}
                {!! $renderField('Tanggal Lembur', $formatDate($overtime->date)) !!}
                {!! $renderField('Waktu Mulai', $overtime->start_time) !!}
                {!! $renderField('Waktu Selesai', $overtime->end_time) !!}
                {!! $renderField('Deskripsi Tugas', $overtime->task_description, ! $loop->last) !!}
            @endforeach
        </div>
    @endif

    @php
        $actionLabel = fn (string $action) => ['requested' => 'Diajukan', 'approved' => 'Disetujui', 'rejected' => 'Ditolak', 'completed' => 'Selesai'][$action] ?? $action;
        $actionBadge = fn (string $action) => match ($action) {
            'approved' => 'badge-pending',
            'rejected' => 'badge-rejected',
            'completed' => 'badge-approved',
            default => 'badge-cancelled',
        };
    @endphp

    <div style="background:#fff;border-radius:16px;box-shadow:0 1px 4px rgba(0,0,0,0.06);overflow:hidden;">
        <div style="padding:14px 18px;border-bottom:1px solid #f1f5f9;">
            <span class="pwa-section-title">Riwayat Approval</span>
        </div>
        @forelse ($record->approvalHistories as $history)
            <div class="pwa-list-row" style="align-items:flex-start;">
                <div style="flex:1;min-width:0;">
                    <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;">
                        <p style="font-size:13px;font-weight:600;color:#1e293b;margin:0;">{{ $history->actor_name ?? '-' }}</p>
                        <span class="badge {{ $actionBadge($history->action) }}">{{ $actionLabel($history->action) }}</span>
                    </div>
                    @if ($history->note)
                        <p style="font-size:12px;color:#64748b;margin-top:4px;">{{ $history->note }}</p>
                    @endif
                    <p style="font-size:11px;color:#94a3b8;margin-top:4px;">{{ $history->created_at?->translatedFormat('d F Y H:i') }}</p>
                </div>
            </div>
        @empty
            <div style="padding:24px 18px;text-align:center;">
                <p style="font-size:13px;color:#64748b;margin:0;">Belum ada riwayat.</p>
            </div>
        @endforelse
    </div>
</div>
</div>
