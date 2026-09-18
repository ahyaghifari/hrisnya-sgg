<div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6" style="gap:12px;">
    @foreach ($this->tiles as $tile)
        <div class="stat-card">
            <div class="pwa-tile-icon {{ $tile['iconClass'] }}" style="position:absolute;top:14px;right:14px;width:32px;height:32px;font-size:12px;"><i class="fa-solid {{ $tile['icon'] }}"></i></div>
            <p style="font-size:22px;font-weight:800;color:{{ $tile['color'] }};letter-spacing:-0.5px;">{{ $tile['value'] }}</p>
            <p style="font-size:11px;color:var(--text-muted);margin-top:2px;">{{ $tile['label'] }}</p>
        </div>
    @endforeach
</div>
