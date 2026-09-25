@php
    $spLevelColors = [
        'sp1' => ['bg' => '#fef9c3', 'fg' => '#a16207'],
        'sp2' => ['bg' => '#ffedd5', 'fg' => '#c2410c'],
        'sp3' => ['bg' => '#fee2e2', 'fg' => '#b91c1c'],
    ];
@endphp
<div style="grid-column:1/-1;">
    <div class="card-surface accent accent-indigo">
        <div class="section-head-icon" style="margin-bottom:14px;">
            <div class="section-icon"><i class="fa-solid fa-chalkboard-user"></i></div>
            <div>
                <p style="font-size:13px;font-weight:700;color:var(--text-heading);margin:0;">Coaching &amp; SP</p>
                <p style="font-size:11px;color:var(--text-faint);margin-top:2px;">Rencana perbaikan yang mendekati jatuh tempo &amp; rangkuman SP yang sedang berjalan</p>
            </div>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px;">
            <div style="background:var(--surface-alt);border-radius:12px;padding:12px;text-align:center;">
                <p style="font-size:18px;font-weight:800;color:var(--text-heading);margin:0;">{{ $this->total }}</p>
                <p style="font-size:10.5px;color:var(--text-muted);margin-top:2px;">Total Coaching</p>
            </div>
            <div style="background:var(--surface-alt);border-radius:12px;padding:12px;text-align:center;">
                <p style="font-size:18px;font-weight:800;color:var(--text-heading);margin:0;">{{ $this->activeSpTotal }}</p>
                <p style="font-size:10.5px;color:var(--text-muted);margin-top:2px;">SP Sedang Aktif</p>
            </div>
        </div>

        <p style="font-size:12px;font-weight:700;color:var(--text-heading);margin:0 0 8px;">Rencana Perbaikan Akan Jatuh Tempo</p>
        <div class="pwa-list" style="margin-bottom:16px;">
            @forelse ($this->upcomingDue as $coach)
                <div class="pwa-list-row">
                    <div class="pwa-tile-icon warn"><i class="fa-solid fa-chalkboard-user"></i></div>
                    <div style="flex:1;min-width:0;">
                        <p style="font-size:13px;font-weight:600;color:var(--text-heading);margin:0;">{{ $coach->user?->name ?? '-' }}</p>
                        <p style="font-size:11px;color:var(--text-muted);margin-top:2px;">{{ $coach->title }} &middot; Coach: {{ $coach->coachBy?->name ?? '-' }}</p>
                    </div>
                    <span class="badge badge-pending">{{ \Illuminate\Support\Carbon::parse($coach->improvement_due_date)->translatedFormat('d M Y') }}</span>
                </div>
            @empty
                <p style="font-size:12.5px;color:var(--text-muted);padding:14px 16px;">Tidak ada rencana perbaikan yang mendekati jatuh tempo.</p>
            @endforelse
        </div>

        <p style="font-size:12px;font-weight:700;color:var(--text-heading);margin:0 0 8px;">Karyawan yang Sedang Kena SP</p>
        <div class="pwa-list">
            @forelse ($this->activeSpList as $sp)
                @php $spColor = $spLevelColors[$sp->sp_level->value] ?? ['bg' => '#f1f5f9', 'fg' => '#64748b']; @endphp
                <div class="pwa-list-row">
                    <div class="pwa-tile-icon warn"><i class="fa-solid fa-triangle-exclamation"></i></div>
                    <div style="flex:1;min-width:0;">
                        <p style="font-size:13px;font-weight:600;color:var(--text-heading);margin:0;">{{ $sp->user?->name ?? '-' }}</p>
                        <p style="font-size:11px;color:var(--text-muted);margin-top:2px;">
                            Berlaku s/d {{ $sp->end_date ? $sp->end_date->translatedFormat('d M Y') : 'tidak pernah berakhir' }}
                        </p>
                    </div>
                    <span class="badge" style="background:{{ $spColor['bg'] }};color:{{ $spColor['fg'] }};">{{ $sp->sp_level->getLabel() }}</span>
                </div>
            @empty
                <p style="font-size:12.5px;color:var(--text-muted);padding:14px 16px;">Tidak ada karyawan yang sedang kena SP.</p>
            @endforelse
        </div>
    </div>
</div>
