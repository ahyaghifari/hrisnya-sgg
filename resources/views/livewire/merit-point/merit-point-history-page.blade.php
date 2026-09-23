<div>
    <x-page-header title="Riwayat Merit Point" size="lg" />

    <div class="pwa-list">
        @forelse ($transactions as $tx)
            <div class="pwa-list-row" style="align-items:flex-start;">
                <div class="pwa-tile-icon {{ $tx->points >= 0 ? 'teal' : 'warn' }}">
                    <i class="fa-solid {{ $tx->points >= 0 ? 'fa-arrow-up' : 'fa-arrow-down' }}"></i>
                </div>
                <div style="flex:1;min-width:0;">
                    <p class="pwa-row-title">{{ $tx->rule?->name ?? 'Manual' }}</p>
                    <span style="font-size:11.5px;color:var(--text-muted);">{{ $tx->reason }}</span>
                    <br>
                    <span style="font-size:11px;color:var(--text-faint);">{{ $tx->created_at->translatedFormat('d M Y H:i') }}</span>
                </div>
                <span style="font-size:15px;font-weight:800;color:{{ $tx->points >= 0 ? '#047C54' : '#b91c1c' }};white-space:nowrap;">
                    {{ $tx->points >= 0 ? '+' : '' }}{{ $tx->points }}
                </span>
            </div>
        @empty
            <div class="pwa-list-row" style="opacity:0.7;">
                <div style="flex:1;min-width:0;">
                    <p class="pwa-row-title">Belum ada transaksi Merit Point</p>
                    <span style="font-size:11.5px;color:var(--text-muted);">Riwayat Merit Point Anda akan muncul di sini.</span>
                </div>
            </div>
        @endforelse
    </div>
</div>
