<div class="card-pair">

    <!-- STAT: MENUNGGU -->
    <div class="stat-card bento-stat-1">
        <div class="stat-card-deco" style="background:#f59e0b;"></div>
        <div style="width:36px;height:36px;background:#fef3c7;border-radius:10px;display:flex;align-items:center;justify-content:center;margin-bottom:16px;">
            <i class="fa-solid fa-hourglass-half" style="color:#d97706;font-size:14px;"></i>
        </div>
        <p style="font-size:38px;font-weight:800;color:#1e293b;letter-spacing:-1.5px;line-height:1;">{{ $pending }}</p>
        <p style="font-size:13px;font-weight:600;color:#64748b;margin-top:6px;">Menunggu Persetujuan</p>
        <p style="font-size:11.5px;color:#64748b;margin-top:3px;">Sedang diproses</p>
    </div>

    <!-- STAT: DISETUJUI -->
    <div class="stat-card bento-stat-2">
        <div class="stat-card-deco" style="background:#047C54;"></div>
        <div style="width:36px;height:36px;background:#dcfce7;border-radius:10px;display:flex;align-items:center;justify-content:center;margin-bottom:16px;">
            <i class="fa-solid fa-circle-check" style="color:#15803d;font-size:14px;"></i>
        </div>
        <p style="font-size:38px;font-weight:800;color:#1e293b;letter-spacing:-1.5px;line-height:1;">{{ $approvedThisMonth }}</p>
        <p style="font-size:13px;font-weight:600;color:#64748b;margin-top:6px;">Disetujui Bulan Ini</p>
        <p style="font-size:11.5px;color:#64748b;margin-top:3px;">{{ now()->translatedFormat('F Y') }}</p>
    </div>

</div>
