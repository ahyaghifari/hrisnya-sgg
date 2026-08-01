<div>
<x-page-header title="Detail Perubahan Status Karyawan" :back="route('kepegawaian.mutasi-karyawan')" />

<x-approval-timeline :timeline="$this->timeline" />

@php
    $type = \App\Enum\UserTransitionTypeEnum::from($record->transition_type);
    $status = \App\Enum\UserTransitionStatusEnum::from($record->status);
    $statusBadge = match ($status) {
        \App\Enum\UserTransitionStatusEnum::APPROVED => 'badge-approved',
        \App\Enum\UserTransitionStatusEnum::REJECTED => 'badge-rejected',
        default => 'badge-pending',
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

    $formatDate = fn (?string $date) => $date ? \Illuminate\Support\Carbon::parse($date)->translatedFormat('d F Y') : null;
@endphp

@if ($this->canApprove)
    <div style="background:var(--surface);border-radius:16px;box-shadow:var(--shadow-card);padding:18px;margin-bottom:16px;">
        <h3 style="font-size:13.5px;font-weight:700;color:var(--text-heading);margin:0 0 14px;">Aksi Persetujuan</h3>
        <div style="display:flex;gap:10px;flex-wrap:wrap;">
            <button type="button" wire:click="approve" wire:confirm="Setujui perubahan status karyawan ini? Perubahan akan langsung diterapkan ke data karyawan (jabatan/role/status aktif)." class="btn-primary">
                <i class="fa-solid fa-check"></i> Setujui
            </button>
            <button type="button" wire:click="reject" wire:confirm="Tolak pengajuan ini?" class="btn-ghost" style="color:var(--danger-text);border-color:var(--danger-soft-border);">
                <i class="fa-solid fa-xmark"></i> Tolak
            </button>
        </div>
    </div>
@endif

<div class="grid grid-cols-1 md:grid-cols-2" style="gap:16px;align-items:start;">
    <div style="background:var(--surface);border-radius:16px;box-shadow:var(--shadow-card);padding:18px;">
        {!! $sectionHeader('fa-identification', 'Informasi Umum') !!}
        {!! $renderField('Nomor', $record->number) !!}
        {!! $renderField('Karyawan', $record->user?->name) !!}
        {!! $renderField('Jenis Perubahan', $type->label()) !!}
        <div style="display:flex;align-items:center;justify-content:space-between;gap:16px;padding:10px 0;">
            <span style="font-size:12.5px;color:var(--text-muted);">Status</span>
            <span class="badge {{ $statusBadge }}">{{ $status->getLabel() }}</span>
        </div>
        {!! $renderField('Dibuat Oleh', $record->createdBy?->name, false) !!}
    </div>

    @if (in_array($record->transition_type, ['promotion', 'demotion']))
        <div style="background:var(--surface);border-radius:16px;box-shadow:var(--shadow-card);padding:18px;">
            {!! $sectionHeader('fa-briefcase', 'Perubahan Jabatan') !!}
            {!! $renderField('Jabatan Sebelumnya', $record->old_value) !!}
            {!! $renderField('Jabatan Baru', $record->transitionable?->name) !!}
            {!! $renderField('Tanggal Efektif', $formatDate($record->effective_date)) !!}
            {!! $renderField('Role Baru', $record->role?->name, false) !!}
        </div>
    @endif

    @if ($record->transition_type === 'mutation')
        <div style="background:var(--surface);border-radius:16px;box-shadow:var(--shadow-card);padding:18px;">
            {!! $sectionHeader('fa-building', 'Mutasi Perusahaan') !!}
            {!! $renderField('Departemen Sebelumnya', $record->old_value) !!}
            {!! $renderField('Departemen Baru', $record->transitionable?->name, false) !!}
        </div>
    @endif

    @if ($record->transition_type === 'termination')
        <div style="background:var(--surface);border-radius:16px;box-shadow:var(--shadow-card);padding:18px;">
            {!! $sectionHeader('fa-triangle-exclamation', 'Alasan PHK') !!}
            <p style="font-size:13px;color:var(--text-body);margin:0;white-space:pre-line;">{{ $record->reason ?: '-' }}</p>
        </div>
    @endif

    @if ($record->transition_type === 'kelas_change')
        <div style="background:var(--surface);border-radius:16px;box-shadow:var(--shadow-card);padding:18px;">
            {!! $sectionHeader('fa-arrow-trend-up', 'Perubahan Kelas') !!}
            {!! $renderField('Kelas Sebelumnya', $record->old_value) !!}
            {!! $renderField('Kelas Baru', $record->transitionable?->name) !!}
            {!! $renderField('Tanggal Efektif', $formatDate($record->effective_date)) !!}
            {!! $renderField('Alasan', $record->reason, false) !!}
        </div>
    @endif

    @if ($record->transition_type === 'employment_status_change')
        <div style="background:var(--surface);border-radius:16px;box-shadow:var(--shadow-card);padding:18px;">
            {!! $sectionHeader('fa-id-card', 'Pengangkatan Orientasi') !!}
            {!! $renderField('Status Sebelumnya', $record->old_value) !!}
            {!! $renderField('Status Baru', $record->transitionable?->name) !!}
            {!! $renderField('Tanggal Efektif', $formatDate($record->effective_date)) !!}
            {!! $renderField('Kontrak Berlaku Mulai', $formatDate($record->contract_start_date)) !!}
            {!! $renderField('Kontrak Berlaku Sampai', $formatDate($record->contract_end_date)) !!}
            {!! $renderField('Alasan', $record->reason, false) !!}
        </div>
    @endif

    @if ($record->transition_type === 'contract_extension')
        <div style="background:var(--surface);border-radius:16px;box-shadow:var(--shadow-card);padding:18px;">
            {!! $sectionHeader('fa-calendar-days', 'Perpanjangan Kontrak') !!}
            {!! $renderField('Kontrak Sebelumnya Sampai', $formatDate($record->old_value)) !!}
            {!! $renderField('Kontrak Baru Mulai', $formatDate($record->contract_start_date)) !!}
            {!! $renderField('Kontrak Baru Sampai', $formatDate($record->contract_end_date)) !!}
            {!! $renderField('Alasan', $record->reason, false) !!}
        </div>
    @endif

    @if ($record->transition_type === 'demotion')
        <div style="background:var(--surface);border-radius:16px;box-shadow:var(--shadow-card);padding:18px;">
            {!! $sectionHeader('fa-triangle-exclamation', 'Alasan Penilaian Demosi') !!}
            @php
                $reasonLabels = [
                    'poor_performance' => 'Kinerja tidak memenuhi standar',
                    'violation' => 'Pelanggaran kebijakan atau etika kerja',
                    'restructuring' => 'Restrukturisasi organisasi',
                    'personal_request' => 'Permintaan pribadi',
                ];
            @endphp
            {!! $renderField('Alasan', $reasonLabels[$record->reason] ?? $record->reason, false) !!}
        </div>
    @endif

    @if (in_array($record->transition_type, ['promotion', 'demotion']) && $record->detail)
        <div style="background:var(--surface);border-radius:16px;box-shadow:var(--shadow-card);padding:18px;">
            {!! $sectionHeader('fa-chart-bar', 'Evaluasi Kinerja') !!}
            {!! $renderField('Pencapaian Target Kerja', $record->detail->target_achievement) !!}
            {!! $renderField('Komentar Target', $record->detail->target_comment) !!}
            {!! $renderField('Kualitas Pekerjaan', $record->detail->work_quality) !!}
            {!! $renderField('Komentar Kualitas', $record->detail->work_quality_comment) !!}
            {!! $renderField('Produktivitas', $record->detail->productivity_level) !!}
            {!! $renderField('Komentar Produktivitas', $record->detail->productivity_comment, false) !!}
        </div>

        <div style="background:var(--surface);border-radius:16px;box-shadow:var(--shadow-card);padding:18px;">
            {!! $sectionHeader('fa-user-group', 'Analisis Perilaku') !!}
            {!! $renderField('Kepatuhan Tanggung Jawab', $record->detail->responsibility_adherence) !!}
            {!! $renderField('Komentar Tanggung Jawab', $record->detail->responsibility_comment) !!}
            {!! $renderField('Etika & Sikap Kerja', $record->detail->work_ethic) !!}
            {!! $renderField('Komentar Etika', $record->detail->work_ethic_comment) !!}
            {!! $renderField('Hubungan Dengan Rekan Kerja', $record->detail->teamwork) !!}
            {!! $renderField('Komentar Teamwork', $record->detail->teamwork_comment, false) !!}
        </div>

        <div style="background:var(--surface);border-radius:16px;box-shadow:var(--shadow-card);padding:18px;">
            {!! $sectionHeader('fa-lightbulb', 'Pertimbangan Tambahan') !!}
            {!! $renderField('Faktor Eksternal', $record->detail->external_factors) !!}
            {!! $renderField('Permintaan Karyawan', $record->detail->employee_request) !!}
            {!! $renderField('Komentar Lainnya', $record->detail->other_comments, false) !!}
        </div>

        @if ($record->manager_comment)
            <div style="background:var(--surface);border-radius:16px;box-shadow:var(--shadow-card);padding:18px;">
                {!! $sectionHeader('fa-comments', 'Komentar Manager/Kepegawaian') !!}
                <p style="font-size:13px;color:var(--text-body);margin:0;white-space:pre-line;">{{ $record->manager_comment }}</p>
            </div>
        @endif
    @endif

    @if ($record->attachments->isNotEmpty())
        <div style="background:var(--surface);border-radius:16px;box-shadow:var(--shadow-card);padding:18px;">
            {!! $sectionHeader('fa-paperclip', 'Lampiran') !!}
            <div style="display:flex;flex-direction:column;gap:8px;">
                @foreach ($record->attachments as $attachment)
                    <a href="{{ asset('storage/' . $attachment->file_path) }}" target="_blank" style="font-size:12.5px;color:var(--brand);text-decoration:none;display:flex;align-items:center;gap:6px;">
                        <i class="fa-solid fa-link"></i> {{ basename($attachment->file_path) }}
                    </a>
                @endforeach
            </div>
        </div>
    @endif
</div>
</div>
