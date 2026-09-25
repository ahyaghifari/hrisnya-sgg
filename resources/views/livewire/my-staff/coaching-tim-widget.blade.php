<div class="card-surface accent accent-indigo">
    <x-my-staff.widget-head icon="fa-chalkboard-user" title="Coaching Tim" sub="Rencana perbaikan yang akan jatuh tempo" :meta="$loaded ? $this->total.' coaching' : null" />

    @if ($loaded)
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
                <div class="ms-empty">
                    <i class="fa-solid fa-circle-check"></i>
                    <span>Tidak ada rencana perbaikan yang mendekati jatuh tempo.</span>
                </div>
            @endforelse
        </div>
    @else
        <p class="ms-loading">Memuat data tim...</p>
    @endif
</div>
