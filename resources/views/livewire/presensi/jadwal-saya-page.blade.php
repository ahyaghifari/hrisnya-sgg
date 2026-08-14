<div>
    <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;margin-bottom:14px;">
        <div style="display:flex;align-items:center;gap:10px;">
            <button type="button" wire:click="previousMonth" class="btn-ghost" style="padding:7px 10px;">
                <i class="fa-solid fa-chevron-left"></i>
            </button>
            <span style="font-size:15px;font-weight:700;color:var(--text-heading);min-width:150px;text-align:center;">{{ $this->monthLabel }}</span>
            <button type="button" wire:click="nextMonth" class="btn-ghost" style="padding:7px 10px;">
                <i class="fa-solid fa-chevron-right"></i>
            </button>
            <button type="button" wire:click="goToToday" class="btn-ghost" style="font-size:11px;padding:7px 12px;">
                Hari Ini
            </button>
        </div>
    </div>

    <div style="display:flex;flex-wrap:wrap;gap:12px;align-items:center;margin-bottom:10px;font-size:10.5px;color:var(--text-muted);">
        <span style="display:inline-flex;align-items:center;gap:5px;"><span style="width:9px;height:9px;border-radius:50%;background:var(--brand-success);"></span> Ada Jadwal</span>
        <span style="display:inline-flex;align-items:center;gap:5px;"><span style="width:9px;height:9px;border-radius:50%;background:var(--danger-text);"></span> Libur</span>
        <span style="display:inline-flex;align-items:center;gap:5px;"><span style="width:9px;height:9px;border-radius:50%;background:var(--warn-soft-text);"></span> Hari Libur Nasional</span>
    </div>

    <div style="background:var(--surface);border-radius:12px;box-shadow:var(--shadow-card);overflow:auto;">
        <div style="display:grid;grid-template-columns:repeat(7,minmax(96px,1fr));min-width:700px;">
            @foreach (['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'] as $dayName)
                <div style="padding:8px 6px;text-align:center;font-size:11px;font-weight:700;color:var(--text-muted);border-bottom:1.5px solid var(--border);">
                    {{ $dayName }}
                </div>
            @endforeach

            @foreach ($this->weeks as $week)
                @foreach ($week as $day)
                    @php
                        $schedule = $day['schedule'];
                        $state = $schedule['state'] ?? null;
                        $bg = match ($state) {
                            'holiday' => 'var(--warn-soft-bg)',
                            'off' => 'var(--danger-soft-bg)',
                            'scheduled' => $schedule['color'] ? 'color-mix(in srgb, ' . $schedule['color'] . ' 16%, var(--surface))' : 'var(--info-soft-bg)',
                            default => 'transparent',
                        };
                    @endphp
                    <div
                        wire:key="day-{{ $day['date']->toDateString() }}"
                        style="min-height:76px;padding:6px;border-bottom:1px solid var(--border-soft);border-right:1px solid var(--border-soft);{{ $day['is_current_month'] ? "background:{$bg};" : 'background:var(--surface-alt);' }}{{ $day['is_today'] ? 'box-shadow:inset 0 0 0 2px var(--brand);' : '' }}"
                    >
                        <div style="font-size:11px;font-weight:{{ $day['is_today'] ? '800' : '600' }};color:{{ $day['is_current_month'] ? 'var(--text-heading)' : 'var(--text-faint)' }};margin-bottom:4px;">
                            {{ $day['date']->format('d') }}
                        </div>

                        @if ($day['is_current_month'] && $schedule)
                            @if ($state === 'holiday')
                                <div style="font-size:10px;color:var(--warn-soft-text);font-weight:600;line-height:1.3;">{{ $schedule['label'] }}</div>
                            @elseif ($state === 'off')
                                <div style="font-size:10px;color:var(--danger-soft-text);font-weight:700;">Libur</div>
                            @elseif ($state === 'scheduled')
                                <div style="font-size:10.5px;font-weight:700;color:var(--text-heading);line-height:1.3;">{{ $schedule['label'] }}</div>
                                @if ($schedule['detail'])
                                    <div style="font-size:9.5px;color:var(--text-muted);">{{ $schedule['detail'] }}</div>
                                @endif
                            @endif
                        @elseif ($day['is_current_month'])
                            <div style="font-size:9.5px;color:var(--text-faint);">–</div>
                        @endif
                    </div>
                @endforeach
            @endforeach
        </div>
    </div>
</div>
