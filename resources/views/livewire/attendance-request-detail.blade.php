<div>
<x-page-header title="Detail Pengajuan" :back="route('my-attendance-requests')" />

<x-approval-timeline :timeline="$this->timeline" />

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

{{-- Info kuota pemohon — supaya approver bisa lihat sisa jatah SEBELUM memutuskan, ditaruh
di atas Aksi Persetujuan. Lihat planning/2026-07-19-revisi-absensi-cutoff-kuota-izin-widget-pimpinan.md §8.
Redesign: angka "Sisa" jadi fokus utama (besar, warna status) + progress bar, rincian lain
jadi chip kecil di bawahnya — supaya approver bisa ambil keputusan sekilas lihat, tidak perlu
baca daftar angka satu-satu. Cuma relevan SAAT approver mau memutuskan — begitu sudah
disetujui/ditolak/dibatalkan, kuota ini tidak lagi jadi pertimbangan apa pun, jadi disembunyikan. --}}
@if ($this->quotaInfo && $this->canApprove)
    @php
        $kind = $this->quotaInfo['kind'];
        $balance = $this->quotaInfo['balance'] ?? null;
        $periode = $this->quotaInfo['periode'] ?? null;
        $formatPeriod = fn ($start, $end) => $start->translatedFormat('d M Y') . ' – ' . $end->translatedFormat('d M Y');

        // Cuti Tahunan punya 2 batas independen sekaligus — kuota per TAHUN (LeaveTypes::
        // quota_days, mis. 12 hari) DAN batas akumulasi per PERIODE bulanan (setting
        // cuti_tahunan_max_per_bulan, mis. maks 5 hari/bulan). Keduanya ditampilkan
        // berdampingan sebagai 2 kartu terpisah — bukan satu jadi "hero" dan satu jadi chip
        // kecil — supaya approver tidak salah kira cuma ada 1 batas.
        if ($kind === 'leave' && $periode) {
            $chips = [['label' => 'Cuti Bersama (mengurangi kuota tahun)', 'value' => "-{$balance['collective_leave_days']} hari"]];
            $entries = [
                [
                    'title' => 'Sisa Kuota Tahun Ini',
                    'quota' => (int) $balance['quota'],
                    'used' => (int) $balance['used'],
                    'available' => (int) $balance['available'],
                    'unlimited' => false,
                    'periodLabel' => null,
                    'variant' => 'year',
                ],
                [
                    'title' => 'Sisa Kuota Periode Ini',
                    'quota' => (int) $periode['max'],
                    'used' => (int) $periode['used'],
                    'available' => $periode['available'] !== null ? (int) $periode['available'] : null,
                    'unlimited' => (int) $periode['max'] <= 0,
                    'periodLabel' => $formatPeriod($periode['period_start'], $periode['period_end']),
                    'variant' => 'status',
                ],
            ];
        } elseif ($kind === 'leave') {
            $chips = [['label' => 'Cuti Bersama', 'value' => "-{$balance['collective_leave_days']} hari"]];
            $entries = [[
                'title' => 'Sisa Kuota Tahun Ini',
                'quota' => (int) $balance['quota'],
                'used' => (int) $balance['used'],
                'available' => (int) $balance['available'],
                'unlimited' => false,
                'periodLabel' => null,
                'variant' => 'year',
            ]];
        } else {
            $usage = $this->quotaInfo['usage'];
            $chips = [];
            $entries = [[
                'title' => 'Sisa Kuota',
                'quota' => (int) $usage['max'],
                'used' => (int) $usage['used'],
                'available' => $usage['available'] !== null ? (int) $usage['available'] : null,
                'unlimited' => (int) $usage['max'] <= 0,
                'periodLabel' => $formatPeriod($usage['period_start'], $usage['period_end']),
                'variant' => 'status',
            ]];
        }
    @endphp
    <div style="background:var(--surface);border-radius:16px;box-shadow:var(--shadow-card);padding:18px;margin-bottom:16px;overflow:hidden;">
        {!! $sectionHeader('fa-chart-simple', 'Kuota Pemohon') !!}

        <div style="display:flex;flex-wrap:wrap;gap:12px;margin:12px 0 14px;">
            @foreach ($entries as $entry)
                @php
                    $quota = $entry['quota'];
                    $used = $entry['used'];
                    $available = $entry['available'];
                    $unlimited = $entry['unlimited'];

                    // Kartu "Tahun" sengaja SELALU pakai warna netral (biru brand) — kuota
                    // tahunan cuma informasi latar, bukan constraint yang aktif diperiksa saat
                    // ini. Kartu "Periode"/Izin tetap pakai warna dinamis merah/kuning/hijau
                    // karena itu batas yang benar-benar relevan untuk keputusan approve bulan
                    // ini — biar approver tidak salah kira dua kartu ini setara pentingnya.
                    $status = $entry['variant'] === 'year' ? 'year' : match (true) {
                        $unlimited => 'neutral',
                        $available === null => 'neutral',
                        $available <= 0 => 'danger',
                        $quota > 0 && $available <= max(1, (int) floor($quota * 0.25)) => 'warn',
                        default => 'success',
                    };
                    $colors = [
                        'danger' => ['text' => 'var(--danger-text)', 'bar' => '#dc2626', 'bg' => 'var(--danger-soft-bg)', 'border' => 'var(--danger-soft-border)'],
                        'warn' => ['text' => '#d97706', 'bar' => '#d97706', 'bg' => 'var(--warn-soft-bg)', 'border' => 'var(--warn-soft-border)'],
                        'success' => ['text' => 'var(--brand-success)', 'bar' => 'var(--brand-success)', 'bg' => 'rgba(4,124,84,0.08)', 'border' => 'rgba(4,124,84,0.3)'],
                        'neutral' => ['text' => 'var(--text-heading)', 'bar' => 'var(--brand)', 'bg' => 'rgba(4,44,108,0.06)', 'border' => 'var(--border-soft)'],
                        'year' => ['text' => 'var(--brand)', 'bar' => 'var(--brand)', 'bg' => 'rgba(4,44,108,0.06)', 'border' => 'rgba(4,44,108,0.25)'],
                    ][$status];
                    $percentUsed = ($quota > 0 && $available !== null) ? max(0, min(100, round((($quota - $available) / $quota) * 100))) : 0;
                @endphp
                <div style="flex:1;min-width:220px;padding:14px;border-radius:12px;background:{{ $colors['bg'] }};border:1px solid {{ $colors['border'] }};">
                    <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;flex-wrap:wrap;margin-bottom:6px;">
                        <p style="margin:0;font-size:10.5px;font-weight:600;text-transform:uppercase;letter-spacing:0.03em;color:var(--text-muted);">
                            {{ $unlimited ? $entry['title'] . ' (Terpakai)' : $entry['title'] }}
                        </p>
                        @if ($entry['periodLabel'])
                            <span style="font-size:10px;font-weight:600;color:var(--text-muted);white-space:nowrap;"><i class="fa-solid fa-calendar-days"></i> {{ $entry['periodLabel'] }}</span>
                        @endif
                    </div>
                    <p style="margin:0 0 8px;font-size:26px;font-weight:800;line-height:1;color:{{ $colors['text'] }};">
                        {{ $unlimited ? $used : $available }}
                        <span style="font-size:12px;font-weight:600;color:var(--text-muted);">hari</span>
                    </p>

                    @if ($unlimited)
                        <span class="badge" style="background:{{ $colors['bg'] }};color:{{ $colors['text'] }};border:1px solid {{ $colors['border'] }};">
                            <i class="fa-solid fa-infinity"></i> Tidak Dibatasi
                        </span>
                    @else
                        <div style="display:flex;justify-content:space-between;font-size:10.5px;color:var(--text-muted);margin-bottom:4px;">
                            <span>Terpakai {{ $percentUsed }}%</span>
                            <span>dari {{ $quota }} hari</span>
                        </div>
                        <div class="progress-track" style="height:7px;">
                            <div class="progress-fill" style="width:{{ $percentUsed }}%;background:{{ $colors['bar'] }};"></div>
                        </div>
                        @if ($status === 'danger')
                            <p style="margin:6px 0 0;font-size:10.5px;font-weight:600;color:{{ $colors['text'] }};"><i class="fa-solid fa-triangle-exclamation"></i> Sudah habis/terlampaui.</p>
                        @elseif ($status === 'warn')
                            <p style="margin:6px 0 0;font-size:10.5px;font-weight:600;color:{{ $colors['text'] }};"><i class="fa-solid fa-circle-exclamation"></i> Tinggal sedikit.</p>
                        @endif
                    @endif
                </div>
            @endforeach
        </div>

        @if (count($chips) > 0)
            <div style="display:flex;flex-wrap:wrap;gap:8px;">
                @foreach ($chips as $chip)
                    <div style="display:flex;align-items:center;gap:6px;padding:6px 10px;border-radius:8px;background:var(--surface-alt);font-size:11.5px;">
                        <span style="color:var(--text-muted);">{{ $chip['label'] }}</span>
                        <span style="font-weight:700;color:var(--text-body);">{{ $chip['value'] }}</span>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
@endif

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
        {!! $sectionHeader('fa-user', 'Informasi Karyawan') !!}
        {!! $renderField('Nama Karyawan', $record->user->name) !!}
        {!! $renderField('NIP', $record->user->nip) !!}
        {!! $renderField('Departemen', $profile?->department?->name) !!}
        {!! $renderField('Tipe Karyawan', $profile?->employeeType?->name, false) !!}
    </div>

    <div style="background:var(--surface);border-radius:16px;box-shadow:var(--shadow-card);padding:18px;">
        {!! $sectionHeader('fa-file-lines', 'Informasi Pengajuan') !!}
        {!! $renderField('Tipe Request', $typeLabel) !!}
        <div style="display:flex;align-items:center;justify-content:space-between;gap:16px;padding:10px 0;">
            <span style="font-size:12.5px;color:var(--text-muted);">Status</span>
            <span class="badge {{ $statusBadge }}">{{ $statusLabel }}</span>
        </div>
    </div>

    @php
        $formatDate = fn (?string $date) => $date ? \Illuminate\Support\Carbon::parse($date)->translatedFormat('d F Y') : null;
    @endphp

    @if ($record->type === 'leave' && $record->leaveDetails->isNotEmpty())
        @php $leave = $record->leaveDetails->first(); @endphp
        <div style="background:var(--surface);border-radius:16px;box-shadow:var(--shadow-card);padding:18px;">
            {!! $sectionHeader('fa-umbrella-beach', 'Detail Cuti') !!}
            {!! $renderField('Tipe Cuti', $leave->leave_type) !!}
            {!! $renderField('Tanggal Mulai', $formatDate($leave->start_date)) !!}
            {!! $renderField('Tanggal Selesai', $formatDate($leave->end_date)) !!}
            {!! $renderField('Total Hari', $leave->total_days ? "{$leave->total_days} hari" : null, false) !!}
        </div>
    @endif

    @if ($record->type === 'permission' && $record->permissionDetails->isNotEmpty())
        @php $permission = $record->permissionDetails->first(); @endphp
        <div style="background:var(--surface);border-radius:16px;box-shadow:var(--shadow-card);padding:18px;">
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
        <div style="background:var(--surface);border-radius:16px;box-shadow:var(--shadow-card);padding:18px;">
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

    <div style="background:var(--surface);border-radius:16px;box-shadow:var(--shadow-card);overflow:hidden;">
        <div style="padding:14px 18px;border-bottom:1px solid var(--border-soft);">
            <span class="pwa-section-title">Riwayat Approval</span>
        </div>
        @forelse ($record->approvalHistories as $history)
            <div class="pwa-list-row" style="align-items:flex-start;">
                <div style="flex:1;min-width:0;">
                    <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;">
                        <p style="font-size:13px;font-weight:600;color:var(--text-heading);margin:0;">{{ $history->actor_name ?? '-' }}</p>
                        <span class="badge {{ $actionBadge($history->action) }}">{{ $actionLabel($history->action) }}</span>
                    </div>
                    @if ($history->note)
                        <p style="font-size:12px;color:var(--text-muted);margin-top:4px;">{{ $history->note }}</p>
                    @endif
                    <p style="font-size:11px;color:var(--text-faint);margin-top:4px;">{{ $history->created_at?->translatedFormat('d F Y H:i') }}</p>
                </div>
            </div>
        @empty
            <div style="padding:24px 18px;text-align:center;">
                <p style="font-size:13px;color:var(--text-muted);margin:0;">Belum ada riwayat.</p>
            </div>
        @endforelse
    </div>
</div>
</div>
