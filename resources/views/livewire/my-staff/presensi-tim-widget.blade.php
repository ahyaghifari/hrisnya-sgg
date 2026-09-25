<div class="card-surface accent accent-green">
    <x-my-staff.widget-head icon="fa-clipboard-check" title="Presensi Tim" :sub="$loaded ? $this->totalTeam.' bawahan' : null" />

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

            <div wire:key="'presensi-chart-'.{{ $mode }}" style="position:relative;width:100%;height:180px;margin-bottom:14px;">
                <canvas data-chart-config="{{ json_encode($this->chartConfig) }}"></canvas>
            </div>

            @php
                $harian = $mode === 'harian';
                $stats = $harian ? $this->todayStats : $this->periodStats;
                $tiles = $harian
                    ? [
                        ['Hadir', $stats['present'] ?? 0, 'green', 'fa-circle-check'],
                        ['Terlambat', $stats['late'] ?? 0, 'amber', 'fa-clock'],
                        ['Cuti', $stats['leave'] ?? 0, 'blue', 'fa-umbrella-beach'],
                        ['Izin', $stats['permit'] ?? 0, 'gold', 'fa-file-pen'],
                        ['Belum Presensi', $stats['not_recorded'] ?? 0, 'muted', 'fa-hourglass-half'],
                    ]
                    : [
                        ['Hari Hadir', $stats['present_days'] ?? 0, 'green', 'fa-circle-check'],
                        ['Hari Telat', $stats['late_days'] ?? 0, 'amber', 'fa-clock'],
                        ['Hari Cuti', $stats['leave_days'] ?? 0, 'blue', 'fa-umbrella-beach'],
                        ['Hari Izin', $stats['permit_days'] ?? 0, 'gold', 'fa-file-pen'],
                        ['Menit Telat', $stats['total_late_minutes'] ?? 0, 'muted', 'fa-stopwatch'],
                    ];
            @endphp
            <div class="grid grid-cols-2 md:grid-cols-5" style="gap:10px;">
                @foreach ($tiles as [$label, $value, $tone, $icon])
                    <div class="ms-stat ms-t-{{ $tone }}">
                        <span class="ms-stat-icon"><i class="fa-solid {{ $icon }}"></i></span>
                        <div>
                            <p class="ms-stat-num">{{ $value }}</p>
                            <p class="ms-stat-label">{{ $label }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @else
        <p class="ms-loading">Memuat data tim...</p>
    @endif
</div>
