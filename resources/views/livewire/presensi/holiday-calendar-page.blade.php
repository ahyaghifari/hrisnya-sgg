<div class="p-4 md:p-6">
    <x-page-header title="Kalender Hari Libur" />

    <div class="mt-4" style="display:flex;flex-direction:column;gap:16px;">
        @forelse ($this->holidays as $month => $items)
            <div class="pwa-list">
                <div class="pwa-card-header pwa-card-header-navy">
                    <div class="pwa-card-header-deco-1"></div><div class="pwa-card-header-deco-2"></div>
                    <div class="pwa-card-header-content">
                        <span class="pwa-section-title"><i class="fa-solid fa-calendar-days" style="font-size:12px;margin-right:4px;"></i> {{ $month }}</span>
                    </div>
                </div>
                @foreach ($items as $holiday)
                    <div class="pwa-list-row" style="border-left:4px solid {{ $holiday->is_collective_leave ? '#d97706' : '#64748b' }};padding-left:12px;">
                        <div style="flex:1;min-width:0;">
                            <p class="pwa-row-title">{{ $holiday->name }}</p>
                            <span style="font-size:11px;color:var(--text-muted);">{{ $holiday->date->translatedFormat('l, j F Y') }}</span>
                        </div>
                        @if ($holiday->is_collective_leave)
                            <span class="badge badge-pending">Cuti Bersama</span>
                        @endif
                    </div>
                @endforeach
            </div>
        @empty
            <div class="pwa-list-row" style="opacity:0.7;border-left:4px solid var(--border);padding-left:12px;">
                <div style="flex:1;min-width:0;">
                    <p class="pwa-row-title">Belum ada hari libur tahun ini</p>
                </div>
            </div>
        @endforelse
    </div>
</div>
