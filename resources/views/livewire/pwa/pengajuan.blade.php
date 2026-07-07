<div>
<div class="mb-6">
    <p class="text-xs text-slate-500 mb-1">{{ now()->translatedFormat('l, d F Y') }}</p>
    <h1 class="text-[22px] font-extrabold text-slate-900 tracking-tight">Pengajuan</h1>
</div>

{{-- AKSI CEPAT: reuse form pengajuan cuti/izin/lembur yang sudah ada, bukan bikin form baru --}}
<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-bottom:24px;">
    <a href="{{ route('pwa.my-attendance-requests.leave') }}" wire:navigate class="pwa-action-btn navy">
        <div class="pwa-action-btn-deco-1"></div><div class="pwa-action-btn-deco-2"></div>
        <div class="pwa-action-btn-content">
            <i class="fa-solid fa-umbrella-beach"></i>
            <span>Cuti</span>
        </div>
    </a>
    <a href="{{ route('pwa.my-attendance-requests.permission') }}" wire:navigate class="pwa-action-btn gold">
        <div class="pwa-action-btn-deco-1"></div><div class="pwa-action-btn-deco-2"></div>
        <div class="pwa-action-btn-content">
            <i class="fa-solid fa-clock"></i>
            <span>Izin</span>
        </div>
    </a>
    <a href="{{ route('pwa.my-attendance-requests.overtime') }}" wire:navigate class="pwa-action-btn green">
        <div class="pwa-action-btn-deco-1"></div><div class="pwa-action-btn-deco-2"></div>
        <div class="pwa-action-btn-content">
            <i class="fa-solid fa-briefcase-clock"></i>
            <span>Lembur</span>
        </div>
    </a>
</div>

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;">
    <span class="pwa-section-title">Riwayat Pengajuan</span>
</div>

@php
    $typeLabel = fn (string $type) => ['leave' => 'Cuti', 'permission' => 'Izin', 'overtime' => 'Lembur'][$type] ?? $type;
    $typeIcon = fn (string $type) => ['leave' => 'fa-umbrella-beach', 'permission' => 'fa-clock', 'overtime' => 'fa-briefcase-clock'][$type] ?? 'fa-file-lines';
    $typeTile = fn (string $type) => ['leave' => 'neutral', 'permission' => 'warn', 'overtime' => 'done'][$type] ?? 'neutral';
    $statusBadge = fn (string $status) => match ($status) {
        'approved' => 'badge-approved',
        'in_review' => 'badge-pending',
        'rejected', 'failed' => 'badge-rejected',
        default => 'badge-cancelled',
    };
    $statusLabel = fn (string $status) => \App\Enum\AttendanceReqStatus::from($status)->getLabel();
    $requests = $this->requests;
@endphp

@if ($requests->isEmpty())
    <div class="pwa-list" style="padding:32px 16px;text-align:center;">
        <p style="font-size:13px;color:#64748b;margin:0;">Belum ada pengajuan.</p>
    </div>
@else
    <div class="pwa-list">
        @foreach ($requests as $record)
            <a href="{{ route('pwa.my-attendance-requests.show', ['record' => $record]) }}" wire:navigate class="pwa-list-row" style="text-decoration:none;">
                <div class="pwa-tile-icon {{ $typeTile($record->type) }}"><i class="fa-solid {{ $typeIcon($record->type) }}"></i></div>
                <div style="flex:1;min-width:0;">
                    <p style="font-size:13px;font-weight:600;color:#1e293b;">{{ $typeLabel($record->type) }} · {{ $this->keterangan($record) }}</p>
                    <p style="font-size:11px;color:#64748b;margin-top:2px;">{{ $record->created_at?->translatedFormat('j F Y H:i') }}</p>
                    <span class="badge {{ $statusBadge($record->status) }}" style="margin-top:4px;">{{ $statusLabel($record->status) }}</span>
                </div>
                <i class="fa-solid fa-chevron-right"></i>
            </a>
        @endforeach
    </div>
@endif
</div>
