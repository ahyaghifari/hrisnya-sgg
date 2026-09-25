<div class="card-surface accent accent-teal">
    <x-my-staff.widget-head icon="fa-umbrella-beach" title="Cuti Tim" sub="7 hari ke depan" />

    @if ($loaded)
        <div class="pwa-list">
            @forelse ($this->upcomingLeave as $request)
                @php
                    $detail = $request->leaveDetails->first();
                    $start = IlluminateSupportCarbon::parse($detail?->start_date);
                    $range = $detail?->end_date && $detail->end_date !== $detail->start_date;
                @endphp
                <div class="pwa-list-row">
                    <div class="ms-date"><b>{{ $start->translatedFormat('d') }}</b><small>{{ $start->translatedFormat('M') }}</small></div>
                    <div style="flex:1;min-width:0;">
                        <p style="font-size:13px;font-weight:600;color:var(--text-heading);margin:0;">{{ $request->user?->name ?? '-' }}</p>
                        <p style="font-size:11px;color:var(--text-muted);margin-top:2px;">{{ $detail?->leave_type ?? 'Cuti' }}@if ($range) &middot; s/d {{ IlluminateSupportCarbon::parse($detail->end_date)->translatedFormat('d M') }}@endif</p>
                    </div>
                </div>
            @empty
                <div class="ms-empty">
                    <i class="fa-solid fa-mug-hot"></i>
                    <span>Tidak ada bawahan yang cuti dalam 7 hari ke depan.</span>
                </div>
            @endforelse
        </div>
    @else
        <p class="ms-loading">Memuat data tim...</p>
    @endif
</div>
