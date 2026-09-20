<div class="contents">

    <div class="stat-card" style="padding:{{ $compact ? '10px' : '22px' }};display:flex;flex-direction:column;justify-content:space-between;">
        <div class="stat-card-deco" style="background:#d97706;"></div>
        <div style="display:flex;align-items:center;gap:{{ $compact ? '6px' : '10px' }};margin-bottom:{{ $compact ? '6px' : '12px' }};">
            <div style="width:{{ $compact ? '20px' : '34px' }};height:{{ $compact ? '20px' : '34px' }};background:rgba(217,119,6,0.12);border-radius:{{ $compact ? '7px' : '10px' }};display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                <i class="fa-solid fa-clock" style="color:#d97706;font-size:{{ $compact ? '9px' : '13px' }};"></i>
            </div>
            <span style="font-size:{{ $compact ? '10px' : '13px' }};font-weight:700;color:var(--text-body);line-height:1.2;">Izin</span>
        </div>
        <div>
            <span style="font-size:{{ $compact ? '26px' : '36px' }};font-weight:800;color:#d97706;letter-spacing:-1px;">{{ $izinCount }}</span>
            <p style="font-size:{{ $compact ? '9px' : '13px' }};color:var(--text-muted);margin-top:2px;">pada periode ini</p>
        </div>
    </div>

    <div class="stat-card" style="padding:{{ $compact ? '10px' : '22px' }};display:flex;flex-direction:column;justify-content:space-between;">
        <div class="stat-card-deco" style="background:var(--brand-success);"></div>
        <div style="display:flex;align-items:center;gap:{{ $compact ? '6px' : '10px' }};margin-bottom:{{ $compact ? '6px' : '12px' }};">
            <div style="width:{{ $compact ? '20px' : '34px' }};height:{{ $compact ? '20px' : '34px' }};background:rgba(4,124,84,0.12);border-radius:{{ $compact ? '7px' : '10px' }};display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                <i class="fa-solid fa-briefcase-clock" style="color:var(--brand-success);font-size:{{ $compact ? '9px' : '13px' }};"></i>
            </div>
            <span style="font-size:{{ $compact ? '10px' : '13px' }};font-weight:700;color:var(--text-body);line-height:1.2;">Lembur</span>
        </div>
        <div>
            <span style="font-size:{{ $compact ? '26px' : '36px' }};font-weight:800;color:var(--brand-success);letter-spacing:-1px;">{{ $lemburCount }}</span>
            <p style="font-size:{{ $compact ? '9px' : '13px' }};color:var(--text-muted);margin-top:2px;">pada periode ini</p>
        </div>
    </div>

    {{-- Extra Shift (extra + double shift digabung jadi satu angka): cuma untuk karyawan berjadwal
    Shift. Rincian extra vs double di popover hover/klik "Detail" - pola sama LeaveBalanceCard
    (`open` di-declare di .stat-card, hover di mana pun buka panel, tombol buat klik/HP). --}}
    @if ($extraShift)
        <div class="stat-card" x-data="{ open: false }" @mouseenter="open = true" @mouseleave="open = false" @click.outside="open = false"
            style="padding:{{ $compact ? '10px' : '22px' }};display:flex;flex-direction:column;justify-content:space-between;overflow:visible;">
            <div style="position:absolute;inset:0;overflow:hidden;border-radius:inherit;pointer-events:none;">
                <div class="stat-card-deco" style="background:#0ea5e9;"></div>
            </div>
            <div style="display:flex;align-items:center;gap:{{ $compact ? '6px' : '10px' }};margin-bottom:{{ $compact ? '6px' : '12px' }};">
                <div style="width:{{ $compact ? '20px' : '34px' }};height:{{ $compact ? '20px' : '34px' }};background:rgba(14,165,233,0.12);border-radius:{{ $compact ? '7px' : '10px' }};display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                    <i class="fa-solid fa-repeat" style="color:#0ea5e9;font-size:{{ $compact ? '9px' : '13px' }};"></i>
                </div>
                <span style="font-size:{{ $compact ? '10px' : '13px' }};font-weight:700;color:var(--text-body);line-height:1.2;">Extra Shift</span>
            </div>
            <div>
                <div style="display:flex;align-items:center;flex-wrap:wrap;gap:{{ $compact ? '4px' : '8px' }};">
                    <span style="font-size:{{ $compact ? '26px' : '36px' }};font-weight:800;color:#0ea5e9;letter-spacing:-1px;">{{ $extraShift['total'] }}</span>

                    <div style="position:relative;">
                        <button type="button" @click="open = !open"
                            style="display:inline-flex;align-items:center;gap:3px;flex-shrink:0;background:var(--surface-alt);border:1px solid var(--border-soft);border-radius:6px;padding:{{ $compact ? '2px 6px' : '3px 8px' }};font-size:{{ $compact ? '9px' : '11px' }};font-weight:600;color:var(--text-muted);cursor:pointer;font-family:inherit;">
                            <i class="fa-solid fa-circle-info"></i> Detail
                        </button>

                        {{-- Background/teks literal (bukan var) supaya terbaca di light & dark mode. --}}
                        <div x-show="open" x-transition.opacity.duration.150ms
                            style="display:none;position:absolute;top:calc(100% + 6px);right:0;z-index:30;width:210px;background:#ffffff;border:1px solid #e2e8f0;border-radius:12px;box-shadow:0 4px 16px rgba(0,0,0,0.16);padding:14px;">
                            <p style="font-size:12px;font-weight:700;color:#1e293b;margin-bottom:8px;">Rincian Periode Ini</p>
                            <div style="display:flex;justify-content:space-between;align-items:center;font-size:11.5px;margin-bottom:6px;">
                                <span style="display:flex;align-items:center;gap:6px;color:#64748b;">
                                    <span style="width:7px;height:7px;border-radius:50%;background:#0ea5e9;flex-shrink:0;"></span>
                                    Extra Shift
                                </span>
                                <span style="color:#0ea5e9;font-weight:600;">{{ $extraShift['extra'] }} kali</span>
                            </div>
                            <div style="display:flex;justify-content:space-between;align-items:center;font-size:11.5px;margin-bottom:8px;">
                                <span style="display:flex;align-items:center;gap:6px;color:#64748b;">
                                    <span style="width:7px;height:7px;border-radius:50%;background:#b91c1c;flex-shrink:0;"></span>
                                    Double Shift
                                </span>
                                <span style="color:#b91c1c;font-weight:600;">{{ $extraShift['double'] }} kali</span>
                            </div>
                            <hr style="border:none;border-top:1px solid #e2e8f0;margin-bottom:8px;">
                            <div style="display:flex;justify-content:space-between;font-size:12.5px;font-weight:700;color:#1e293b;">
                                <span>Total</span><span>{{ $extraShift['total'] }} kali</span>
                            </div>
                        </div>
                    </div>
                </div>
                <p style="font-size:{{ $compact ? '9px' : '13px' }};color:var(--text-muted);margin-top:2px;">extra periode ini</p>
            </div>
        </div>
    @endif
</div>
