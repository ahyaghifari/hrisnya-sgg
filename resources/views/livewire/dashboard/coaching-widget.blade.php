<div style="grid-column:1/-1;">
    <div class="card-surface accent accent-indigo">
        <div class="section-head-icon" style="margin-bottom:14px;">
            <div class="section-icon"><i class="fa-solid fa-chalkboard-user"></i></div>
            <div>
                <p style="font-size:13px;font-weight:700;color:var(--text-heading);margin:0;">Coaching</p>
                <p style="font-size:11px;color:var(--text-faint);margin-top:2px;">Jumlah coaching &amp; rencana perbaikan yang mendekati jatuh tempo</p>
            </div>
        </div>

        <div style="background:var(--surface-alt);border-radius:12px;padding:12px;text-align:center;margin-bottom:16px;">
            <p style="font-size:18px;font-weight:800;color:var(--text-heading);margin:0;">{{ $this->total }}</p>
            <p style="font-size:10.5px;color:var(--text-muted);margin-top:2px;">Total Coaching</p>
        </div>

        <p style="font-size:12px;font-weight:700;color:var(--text-heading);margin:0 0 8px;">Rencana Perbaikan Akan Jatuh Tempo</p>
        <div class="pwa-list">
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
    </div>
</div>
