<div class="card-surface accent accent-green">
    <div class="section-head-icon" style="margin-bottom:14px;">
        <div class="section-icon"><i class="fa-solid fa-clipboard-check"></i></div>
        <p style="font-size:13px;font-weight:700;color:var(--text-heading);margin:0;">Presensi Tim</p>
    </div>

    @if ($loaded)
        <div>
            <div class="section-nav" style="margin-bottom:14px;">
                <button type="button" wire:click="setMode('harian')" class="section-nav-btn green {{ $mode === 'harian' ? 'is-active' : '' }}"><i class="fa-solid fa-calendar-day"></i> Harian</button>
                <button type="button" wire:click="setMode('periode')" class="section-nav-btn green {{ $mode === 'periode' ? 'is-active' : '' }}"><i class="fa-solid fa-calendar-week"></i> Periode</button>
            </div>

            @if ($mode === 'periode')
                <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:14px;">
                    <input type="date" wire:model.live="dateFrom" class="form-input" style="max-width:160px;">
                    <span style="align-self:center;color:var(--text-faint);">&ndash;</span>
                    <input type="date" wire:model.live="dateTo" class="form-input" style="max-width:160px;">
                </div>
            @endif

            <p style="font-size:11px;color:var(--text-faint);margin-bottom:10px;">{{ $this->totalTeam }} bawahan</p>

            <div wire:key="'presensi-chart-'.{{ $mode }}" style="position:relative;width:100%;height:180px;margin-bottom:14px;">
                <canvas data-chart-config="{{ json_encode($this->chartConfig) }}"></canvas>
            </div>

            @if ($mode === 'harian')
                @php $stats = $this->todayStats; @endphp
                <div class="grid grid-cols-2 md:grid-cols-5" style="gap:8px;">
                    <div style="background:#dcfce7;border-radius:10px;padding:10px;text-align:center;">
                        <p style="font-size:16px;font-weight:800;color:#15803d;margin:0;">{{ $stats['present'] ?? 0 }}</p>
                        <p style="font-size:10px;color:#15803d;margin-top:1px;">Hadir</p>
                    </div>
                    <div style="background:#fef3c7;border-radius:10px;padding:10px;text-align:center;">
                        <p style="font-size:16px;font-weight:800;color:#a16207;margin:0;">{{ $stats['late'] ?? 0 }}</p>
                        <p style="font-size:10px;color:#a16207;margin-top:1px;">Terlambat</p>
                    </div>
                    <div style="background:#dbeafe;border-radius:10px;padding:10px;text-align:center;">
                        <p style="font-size:16px;font-weight:800;color:#1a56c4;margin:0;">{{ $stats['leave'] ?? 0 }}</p>
                        <p style="font-size:10px;color:#1a56c4;margin-top:1px;">Cuti</p>
                    </div>
                    <div style="background:#fef9c3;border-radius:10px;padding:10px;text-align:center;">
                        <p style="font-size:16px;font-weight:800;color:#a16207;margin:0;">{{ $stats['permit'] ?? 0 }}</p>
                        <p style="font-size:10px;color:#a16207;margin-top:1px;">Izin</p>
                    </div>
                    <div style="background:var(--surface-alt);border-radius:10px;padding:10px;text-align:center;">
                        <p style="font-size:16px;font-weight:800;color:var(--text-muted);margin:0;">{{ $stats['not_recorded'] ?? 0 }}</p>
                        <p style="font-size:10px;color:var(--text-muted);margin-top:1px;">Belum Presensi</p>
                    </div>
                </div>
            @else
                @php $stats = $this->periodStats; @endphp
                <div class="grid grid-cols-2 md:grid-cols-5" style="gap:8px;">
                    <div style="background:#dcfce7;border-radius:10px;padding:10px;text-align:center;">
                        <p style="font-size:16px;font-weight:800;color:#15803d;margin:0;">{{ $stats['present_days'] ?? 0 }}</p>
                        <p style="font-size:10px;color:#15803d;margin-top:1px;">Hari Hadir</p>
                    </div>
                    <div style="background:#fef3c7;border-radius:10px;padding:10px;text-align:center;">
                        <p style="font-size:16px;font-weight:800;color:#a16207;margin:0;">{{ $stats['late_days'] ?? 0 }}</p>
                        <p style="font-size:10px;color:#a16207;margin-top:1px;">Hari Telat</p>
                    </div>
                    <div style="background:#dbeafe;border-radius:10px;padding:10px;text-align:center;">
                        <p style="font-size:16px;font-weight:800;color:#1a56c4;margin:0;">{{ $stats['leave_days'] ?? 0 }}</p>
                        <p style="font-size:10px;color:#1a56c4;margin-top:1px;">Hari Cuti</p>
                    </div>
                    <div style="background:#fef9c3;border-radius:10px;padding:10px;text-align:center;">
                        <p style="font-size:16px;font-weight:800;color:#a16207;margin:0;">{{ $stats['permit_days'] ?? 0 }}</p>
                        <p style="font-size:10px;color:#a16207;margin-top:1px;">Hari Izin</p>
                    </div>
                    <div style="background:var(--surface-alt);border-radius:10px;padding:10px;text-align:center;">
                        <p style="font-size:16px;font-weight:800;color:var(--text-muted);margin:0;">{{ $stats['total_late_minutes'] ?? 0 }}</p>
                        <p style="font-size:10px;color:var(--text-muted);margin-top:1px;">Menit Telat</p>
                    </div>
                </div>
            @endif
        </div>
    @else
        <p style="font-size:12px;color:var(--text-faint);">Memuat data tim...</p>
    @endif
</div>
