<div style="{{ $this->ongoingSwaps->isEmpty() ? 'display:none;' : 'margin-top:14px;' }}">
@if ($this->ongoingSwaps->isNotEmpty())
    <p style="font-size:10.5px;font-weight:700;letter-spacing:0.4px;text-transform:uppercase;color:var(--text-muted);margin:0 0 6px;">Tukar Shift Berjalan</p>
    <div class="pwa-list">
        @foreach ($this->ongoingSwaps as $swap)
            <div class="pwa-list-row" style="padding:10px 12px;">
                <div class="pwa-tile-icon warn" style="width:30px;height:30px;font-size:12px;">
                    <i class="fa-solid fa-right-left"></i>
                </div>
                <div style="min-width:0;flex:1;">
                    <p class="pwa-row-title" style="font-size:12px;">dengan {{ $swap->target?->name }}</p>
                    <p style="font-size:11px;color:var(--text-muted);margin:2px 0 0;">{{ $this->keterangan($swap) }}</p>
                </div>
                <span class="badge {{ $this->statusBadgeClass($swap->status) }}" style="flex-shrink:0;">{{ $this->statusLabel($swap->status) }}</span>
            </div>
        @endforeach
    </div>
@endif
</div>
