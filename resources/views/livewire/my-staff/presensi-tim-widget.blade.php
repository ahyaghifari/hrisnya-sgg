<div class="card-surface accent accent-green">
    <x-my-staff.widget-head icon="fa-clipboard-check" title="Presensi Tim" :sub="$loaded ? $this->totalTeam.' staff' : null" />

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
                        ['Hadir', $stats['present'] ?? 0, 'green', 'fa-circle-check', 'present'],
                        ['Terlambat', $stats['late'] ?? 0, 'amber', 'fa-clock', 'late'],
                        ['Cuti', $stats['leave'] ?? 0, 'blue', 'fa-umbrella-beach', 'leave'],
                        ['Izin', $stats['permit'] ?? 0, 'gold', 'fa-file-pen', 'permit'],
                        ['Belum Presensi', $stats['not_recorded'] ?? 0, 'muted', 'fa-hourglass-half', 'not_recorded'],
                    ]
                    : [
                        ['Hari Hadir', $stats['present_days'] ?? 0, 'green', 'fa-circle-check', 'present_days'],
                        ['Hari Telat', $stats['late_days'] ?? 0, 'amber', 'fa-clock', 'late_days'],
                        ['Hari Cuti', $stats['leave_days'] ?? 0, 'blue', 'fa-umbrella-beach', 'leave_days'],
                        ['Hari Izin', $stats['permit_days'] ?? 0, 'gold', 'fa-file-pen', 'permit_days'],
                        ['Menit Telat', $stats['total_late_minutes'] ?? 0, 'muted', 'fa-stopwatch', 'late_minutes'],
                    ];
            @endphp
            <div class="grid grid-cols-2 md:grid-cols-5" style="gap:10px;">
                @foreach ($tiles as [$label, $value, $tone, $icon, $key])
                    <button type="button" wire:click="openModal('{{ $key }}')" class="ms-stat ms-t-{{ $tone }}">
                        <span class="ms-stat-icon"><i class="fa-solid {{ $icon }}"></i></span>
                        <div>
                            <p class="ms-stat-num">{{ $value }}</p>
                            <p class="ms-stat-label">{{ $label }}</p>
                        </div>
                    </button>
                @endforeach
            </div>
        </div>

        @if ($activeModal)
            <div class="modal-overlay ms-modal" wire:click="closeModal">
                <div class="modal-box" onclick="event.stopPropagation()">
                    <h3 style="font-size:16px;font-weight:700;color:var(--text-heading);margin:0 0 4px;">{{ $this->modalTitle }}</h3>
                    <p style="font-size:12px;color:var(--text-muted);margin:0 0 14px;">
                        {{ $this->modalEmployees->count() }} orang{{ $mode === 'harian' ? ' hari ini' : ' pada periode ini' }}.
                    </p>

                    @forelse ($this->modalEmployees as $row)
                        <div style="padding:8px 0;border-bottom:1px solid var(--border-soft);display:flex;justify-content:space-between;gap:12px;">
                            <div>
                                <p style="font-size:12.5px;font-weight:600;color:var(--text-heading);margin:0;">{{ $row['user']->name }}</p>
                                <p style="font-size:11px;color:var(--text-muted);margin:2px 0 0;">{{ $row['user']->profile?->jobPosition?->name ?? '-' }}</p>
                            </div>
                            @if ($row['detail'])
                                <span style="font-size:12px;font-weight:600;color:var(--text-muted);white-space:nowrap;">{{ $row['detail'] }}</span>
                            @endif
                        </div>
                    @empty
                        <p style="font-size:12.5px;color:var(--text-muted);">Tidak ada staff.</p>
                    @endforelse

                    <div style="margin-top:16px;text-align:right;">
                        <button type="button" wire:click="closeModal" class="btn-ghost">Tutup</button>
                    </div>
                </div>
            </div>
        @endif
    @else
        <p class="ms-loading">Memuat data tim...</p>
    @endif
</div>
