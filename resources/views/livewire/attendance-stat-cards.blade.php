<div class="contents">

    <div class="stat-card" style="padding:{{ $compact ? '10px' : '22px' }};display:flex;flex-direction:column;justify-content:space-between;">
        <div class="stat-card-deco" style="background:#d97706;"></div>
        <div style="display:flex;align-items:center;gap:{{ $compact ? '6px' : '10px' }};margin-bottom:{{ $compact ? '6px' : '12px' }};">
            <div style="width:{{ $compact ? '20px' : '34px' }};height:{{ $compact ? '20px' : '34px' }};background:rgba(217,119,6,0.12);border-radius:{{ $compact ? '7px' : '10px' }};display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                <i class="fa-solid fa-clock" style="color:#d97706;font-size:{{ $compact ? '9px' : '13px' }};"></i>
            </div>
            <span style="font-size:{{ $compact ? '10px' : '13px' }};font-weight:700;color:var(--text-body);line-height:1.2;">Jumlah Izin</span>
        </div>
        <div>
            <span style="font-size:{{ $compact ? '26px' : '36px' }};font-weight:800;color:#d97706;letter-spacing:-1px;">{{ $izinCount }}</span>
            <p style="font-size:{{ $compact ? '9px' : '13px' }};color:var(--text-muted);margin-top:2px;">pengajuan bulan ini</p>
        </div>
    </div>

    <div class="stat-card" style="padding:{{ $compact ? '10px' : '22px' }};display:flex;flex-direction:column;justify-content:space-between;">
        <div class="stat-card-deco" style="background:var(--brand-success);"></div>
        <div style="display:flex;align-items:center;gap:{{ $compact ? '6px' : '10px' }};margin-bottom:{{ $compact ? '6px' : '12px' }};">
            <div style="width:{{ $compact ? '20px' : '34px' }};height:{{ $compact ? '20px' : '34px' }};background:rgba(4,124,84,0.12);border-radius:{{ $compact ? '7px' : '10px' }};display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                <i class="fa-solid fa-briefcase-clock" style="color:var(--brand-success);font-size:{{ $compact ? '9px' : '13px' }};"></i>
            </div>
            <span style="font-size:{{ $compact ? '10px' : '13px' }};font-weight:700;color:var(--text-body);line-height:1.2;">Jumlah Lembur</span>
        </div>
        <div>
            <span style="font-size:{{ $compact ? '26px' : '36px' }};font-weight:800;color:var(--brand-success);letter-spacing:-1px;">{{ $lemburCount }}</span>
            <p style="font-size:{{ $compact ? '9px' : '13px' }};color:var(--text-muted);margin-top:2px;">pengajuan bulan ini</p>
        </div>
    </div>

</div>
