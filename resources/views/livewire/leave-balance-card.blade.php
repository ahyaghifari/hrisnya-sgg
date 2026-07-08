<div class="stat-card {{ $gridClass }}" style="padding:{{ $compact ? '10px' : '22px' }};display:flex;flex-direction:column;justify-content:space-between;">
    <div class="stat-card-deco" style="background:{{ $color }};"></div>
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:{{ $compact ? '6px' : '12px' }};">
        <div style="display:flex;align-items:center;gap:{{ $compact ? '6px' : '10px' }};">
            <div style="width:{{ $compact ? '20px' : '34px' }};height:{{ $compact ? '20px' : '34px' }};background:{{ $iconBg }};border-radius:{{ $compact ? '7px' : '10px' }};display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                <i class="fa-solid {{ $icon }}" style="color:{{ $color }};font-size:{{ $compact ? '9px' : '13px' }};"></i>
            </div>
            <span style="font-size:{{ $compact ? '10px' : '13px' }};font-weight:700;color:#374151;line-height:1.2;">{{ $label }}</span>
        </div>
        @unless ($compact)
            <span style="font-size:11px;color:#64748b;background:#dde3ee;padding:3px 8px;border-radius:6px;font-weight:600;">{{ date('Y') }}</span>
        @endunless
    </div>

    @if ($balance)
        @php $pct = $balance['quota'] > 0 ? round(($balance['used'] / $balance['quota']) * 100) : 0; @endphp
        <div>
            <div style="display:flex;align-items:baseline;gap:4px;margin-bottom:{{ $compact ? '5px' : '8px' }};">
                <span style="font-size:{{ $compact ? '18px' : '36px' }};font-weight:800;color:{{ $color }};letter-spacing:-1px;">{{ $balance['available'] }}</span>
                <span style="font-size:{{ $compact ? '9px' : '13px' }};color:#64748b;font-weight:500;">/ {{ $balance['quota'] }} hari</span>
            </div>
            @unless ($compact)
                <div class="progress-track"><div class="progress-fill" style="width:{{ $pct }}%;background:{{ $color }};"></div></div>
                <p style="font-size:11px;color:#64748b;margin-top:5px;text-align:right;">{{ $balance['used'] }} hari terpakai</p>
            @endunless
        </div>
    @else
        <p style="font-size:{{ $compact ? '10px' : '13px' }};color:#64748b;">Data tidak tersedia</p>
    @endif
</div>
