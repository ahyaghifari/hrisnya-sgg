@php
    $rows = [
        ['fa-user', 'Karyawan', $record->user->name, 'var(--brand)'],
        ['fa-user-tie', 'Atasan langsung', $record->supervisor?->name ?? '-', '#7c3aed'],
        ['fa-calendar-days', 'Waktu', $record->scheduled_at->translatedFormat('l, d M Y') . ' · ' . $record->scheduled_at->format('H:i'), '#0891b2'],
        ['fa-location-dot', 'Tempat', $record->location, '#ea580c'],
    ];
    if ($record->note) {
        $rows[] = ['fa-note-sticky', 'Catatan', $record->note, '#64748b'];
    }
    [$statusLabel, $statusIcon, $statusBg, $statusFg] = $record->isCancelled()
        ? ['Dibatalkan', 'fa-ban', '#fee2e2', '#b91c1c']
        : ($record->report ? ['Berita Acara dibuat', 'fa-circle-check', '#dcfce7', '#15803d'] : ['Menunggu klarifikasi', 'fa-hourglass-half', '#fef3c7', '#b45309']);
@endphp

<div class="form-card">
    <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;margin-bottom:18px;">
        <span class="badge" style="background:{{ $statusBg }};color:{{ $statusFg }};font-size:12px;padding:5px 12px;">
            <i class="fa-solid {{ $statusIcon }}" style="margin-right:5px;"></i>{{ $statusLabel }}
        </span>
        @if ($record->report && ($baLink ?? false))
            <a href="{{ route('ba.show', $record->report) }}" wire:navigate class="btn-ghost"><i class="fa-solid fa-file-lines" style="font-size:12px;"></i> Lihat Berita Acara</a>
        @endif
    </div>

    <div style="display:flex;flex-direction:column;gap:16px;">
        @foreach ($rows as [$icon, $label, $value, $color])
            <div style="display:flex;align-items:flex-start;gap:14px;">
                <div style="flex:none;width:38px;height:38px;border-radius:10px;display:flex;align-items:center;justify-content:center;background:color-mix(in srgb, {{ $color }} 13%, transparent);color:{{ $color }};">
                    <i class="fa-solid {{ $icon }}" style="font-size:15px;"></i>
                </div>
                <div style="min-width:0;">
                    <div style="font-size:11px;font-weight:600;letter-spacing:.04em;text-transform:uppercase;color:var(--text-muted);">{{ $label }}</div>
                    <div style="font-size:14.5px;font-weight:600;color:var(--text-heading);margin-top:2px;word-break:break-word;">{{ $value }}</div>
                </div>
            </div>
        @endforeach

        @if ($record->isCancelled() && $record->cancelled_reason)
            <div style="border-radius:10px;padding:10px 14px;background:#fef2f2;color:#b91c1c;font-size:13px;">
                <i class="fa-solid fa-circle-info" style="margin-right:6px;"></i>Alasan pembatalan: {{ $record->cancelled_reason }}
            </div>
        @endif
    </div>
</div>
