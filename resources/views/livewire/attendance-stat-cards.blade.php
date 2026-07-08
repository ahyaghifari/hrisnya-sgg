<div class="contents">

    <div class="stat-card" style="padding:{{ $compact ? '14px' : '22px' }};display:flex;flex-direction:column;justify-content:space-between;">
        <div class="stat-card-deco" style="background:#d97706;"></div>
        <div style="display:flex;align-items:center;gap:{{ $compact ? '8px' : '10px' }};margin-bottom:{{ $compact ? '8px' : '12px' }};">
            <div style="width:{{ $compact ? '26px' : '34px' }};height:{{ $compact ? '26px' : '34px' }};background:rgba(217,119,6,0.12);border-radius:10px;display:flex;align-items:center;justify-content:center;">
                <i class="fa-solid fa-clock" style="color:#d97706;font-size:{{ $compact ? '11px' : '13px' }};"></i>
            </div>
            <span style="font-size:{{ $compact ? '12px' : '13px' }};font-weight:700;color:#374151;">Jumlah Izin</span>
        </div>
        <div>
            <span style="font-size:{{ $compact ? '24px' : '36px' }};font-weight:800;color:#d97706;letter-spacing:-1px;">{{ $izinCount }}</span>
            <p style="font-size:{{ $compact ? '11px' : '13px' }};color:#64748b;margin-top:2px;">pengajuan bulan ini</p>
        </div>
    </div>

    <div class="stat-card" style="padding:{{ $compact ? '14px' : '22px' }};display:flex;flex-direction:column;justify-content:space-between;">
        <div class="stat-card-deco" style="background:#047C54;"></div>
        <div style="display:flex;align-items:center;gap:{{ $compact ? '8px' : '10px' }};margin-bottom:{{ $compact ? '8px' : '12px' }};">
            <div style="width:{{ $compact ? '26px' : '34px' }};height:{{ $compact ? '26px' : '34px' }};background:rgba(4,124,84,0.12);border-radius:10px;display:flex;align-items:center;justify-content:center;">
                <i class="fa-solid fa-briefcase-clock" style="color:#047C54;font-size:{{ $compact ? '11px' : '13px' }};"></i>
            </div>
            <span style="font-size:{{ $compact ? '12px' : '13px' }};font-weight:700;color:#374151;">Jumlah Lembur</span>
        </div>
        <div>
            <span style="font-size:{{ $compact ? '24px' : '36px' }};font-weight:800;color:#047C54;letter-spacing:-1px;">{{ $lemburCount }}</span>
            <p style="font-size:{{ $compact ? '11px' : '13px' }};color:#64748b;margin-top:2px;">pengajuan bulan ini</p>
        </div>
    </div>

</div>
