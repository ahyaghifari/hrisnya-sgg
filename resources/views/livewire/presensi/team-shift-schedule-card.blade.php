<div style="grid-column:1/-1;">
@if ($this->teamMembers->isNotEmpty())
<div class="pwa-list" style="padding-bottom:16px;">
    <div class="pwa-card-header pwa-card-header-primary" style="margin-bottom:14px;">
        <div class="pwa-card-header-deco-1"></div><div class="pwa-card-header-deco-2"></div>
        <div class="pwa-card-header-content">
            <span class="pwa-section-title"><i class="fa-solid fa-users text-xs" style="font-size:12px;margin-right:4px;"></i> Jadwal Shift Tim</span>
            @if ($this->teamScheduleId)
                <a href="{{ route('presensi.jadwal.show', ['schedule' => $this->teamScheduleId, 'tab' => 'penjadwalan']) }}" wire:navigate class="pwa-section-link">Detail Jadwal <i class="fa-solid fa-chevron-right" style="font-size:9px;"></i></a>
            @endif
        </div>
    </div>

    <div style="padding:0 16px;">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;">
            <button type="button" wire:click="previousDay" class="btn-ghost" style="padding:4px 9px;font-size:11px;"><i class="fa-solid fa-chevron-left"></i></button>
            <button type="button" wire:click="goToToday" style="background:none;border:none;cursor:pointer;padding:0;">
                <span style="font-size:12.5px;font-weight:700;color:var(--text-heading);">{{ $this->dateLabel }}</span>
            </button>
            <button type="button" wire:click="nextDay" class="btn-ghost" style="padding:4px 9px;font-size:11px;"><i class="fa-solid fa-chevron-right"></i></button>
        </div>

        <div class="team-shift-list" style="display:flex;flex-direction:column;gap:6px;">
            @foreach ($this->teamSchedule as $row)
                @php
                    $schedule = $row['schedule'];
                    $state = $schedule['state'];
                    $chipBg = match ($state) {
                        'holiday' => 'var(--warn-soft-bg)',
                        'off' => 'var(--danger-soft-bg)',
                        'scheduled' => $schedule['color'] ? 'color-mix(in srgb, ' . $schedule['color'] . ' 18%, var(--surface))' : 'var(--info-soft-bg)',
                        default => 'var(--surface-alt)',
                    };
                    $chipText = match ($state) {
                        'holiday' => 'var(--warn-soft-text)',
                        'off' => 'var(--danger-soft-text)',
                        'scheduled' => $schedule['color'] ?: 'var(--info-soft-text)',
                        default => 'var(--text-faint)',
                    };
                    $label = match ($state) {
                        'holiday' => $schedule['label'],
                        'off' => 'Libur',
                        'scheduled' => $schedule['label'] . ($schedule['detail'] ? " ({$schedule['detail']})" : ''),
                        default => 'Belum ada jadwal',
                    };
                @endphp
                <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;padding:7px 10px;border-radius:8px;background:var(--surface-alt);">
                    <span style="font-size:12.5px;font-weight:600;color:var(--text-heading);">{{ $row['user']->name }}</span>
                    <span style="font-size:11px;font-weight:700;padding:2px 8px;border-radius:99px;background:{{ $chipBg }};color:{{ $chipText }};white-space:nowrap;">{{ $label }}</span>
                </div>
            @endforeach
        </div>

        @if ($this->canCreateExtraShift)
            <livewire:presensi.extra-shift-request-modal :key="'extra-shift-modal'" />
        @endif
    </div>
</div>
@endif
</div>
