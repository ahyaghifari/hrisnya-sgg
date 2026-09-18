<div class="card-surface accent accent-teal">
    <div class="section-head-icon" style="margin-bottom:14px;">
        <div class="section-icon"><i class="fa-solid fa-umbrella-beach"></i></div>
        <p style="font-size:13px;font-weight:700;color:var(--text-heading);margin:0;">Cuti Tim 7 Hari ke Depan</p>
    </div>

    @if ($loaded)
        <div class="pwa-list">
            @forelse ($this->upcomingLeave as $request)
                @php $detail = $request->leaveDetails->first(); @endphp
                <div class="pwa-list-row">
                    <div class="pwa-tile-icon neutral"><i class="fa-solid fa-umbrella-beach"></i></div>
                    <div style="flex:1;min-width:0;">
                        <p style="font-size:13px;font-weight:600;color:var(--text-heading);margin:0;">{{ $request->user?->name ?? '-' }}</p>
                        <p style="font-size:11px;color:var(--text-muted);margin-top:2px;">{{ $detail?->leave_type ?? 'Cuti' }}</p>
                    </div>
                    <span style="font-size:11.5px;color:var(--text-muted);white-space:nowrap;">
                        {{ \Illuminate\Support\Carbon::parse($detail?->start_date)->translatedFormat('d M') }}
                        @if ($detail?->end_date && $detail->end_date !== $detail->start_date)
                            &ndash; {{ \Illuminate\Support\Carbon::parse($detail->end_date)->translatedFormat('d M') }}
                        @endif
                    </span>
                </div>
            @empty
                <p style="font-size:12.5px;color:var(--text-muted);padding:14px 16px;">Tidak ada bawahan yang cuti dalam 7 hari ke depan.</p>
            @endforelse
        </div>
    @else
        <p style="font-size:12px;color:var(--text-faint);">Memuat data tim...</p>
    @endif
</div>
