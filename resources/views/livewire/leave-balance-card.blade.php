<div
    class="stat-card {{ $gridClass }}"
    x-data="{ open: false }"
    @mouseenter="open = true"
    @mouseleave="open = false"
    @click.outside="open = false"
    style="padding:{{ $compact ? '10px' : '22px' }};display:flex;flex-direction:column;justify-content:space-between;overflow:visible;"
>
    {{-- Deco lingkaran dibungkus wrapper clip sendiri (bukan overflow:hidden di .stat-card
    langsung) — card-nya sendiri sekarang overflow:visible supaya popover "Detail" di bawah bisa
    muncul di luar batas card tanpa ke-clip. --}}
    <div style="position:absolute;inset:0;overflow:hidden;border-radius:inherit;pointer-events:none;">
        <div class="stat-card-deco" style="background:{{ $color }};"></div>
    </div>
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:{{ $compact ? '6px' : '12px' }};">
        <div style="display:flex;align-items:center;gap:{{ $compact ? '6px' : '10px' }};">
            <div style="width:{{ $compact ? '20px' : '34px' }};height:{{ $compact ? '20px' : '34px' }};background:{{ $iconBg }};border-radius:{{ $compact ? '7px' : '10px' }};display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                <i class="fa-solid {{ $icon }}" style="color:{{ $color }};font-size:{{ $compact ? '9px' : '13px' }};"></i>
            </div>
            <span style="font-size:{{ $compact ? '10px' : '13px' }};font-weight:700;color:var(--text-body);line-height:1.2;">{{ $label }}</span>
        </div>
        @unless ($compact)
            <span style="font-size:11px;color:var(--text-muted);background:var(--surface-alt);padding:3px 8px;border-radius:6px;font-weight:600;">{{ date('Y') }}</span>
        @endunless
    </div>

    @if ($balance)
        @php
            $pct = $balance['quota'] > 0 ? round(($balance['used'] / $balance['quota']) * 100) : 0;
            $collectiveLeaveDays = $balance['collective_leave_days'] ?? 0;
        @endphp
        <div>
            <div style="display:flex;align-items:center;flex-wrap:wrap;gap:{{ $compact ? '4px' : '8px' }};margin-bottom:{{ $compact ? '3px' : '4px' }};">
                <div style="display:flex;align-items:baseline;gap:4px;">
                    <span style="font-size:{{ $compact ? '26px' : '36px' }};font-weight:800;color:{{ $color }};letter-spacing:-1px;">{{ $balance['available'] }}</span>
                    <span style="font-size:{{ $compact ? '9px' : '13px' }};color:var(--text-muted);font-weight:500;">hari</span>
                </div>

                {{-- Popover "Detail" — position:absolute biasa (pola sama .dropdown/.dropdown-menu
                yang sudah dipakai di nav.blade.php), bukan x-teleport+koordinat JS. Anchor-nya
                wrapper tombol ini sendiri (position:relative), z-index tinggi supaya tampil di
                atas card lain, bisa muncul karena .stat-card di atas sudah overflow:visible.
                Background dipaksa putih literal (bukan var(--surface)) + teks warna gelap literal
                supaya tetap terbaca di light & dark mode. `open` di-declare di `.stat-card` (bukan
                di sini) — hover di mana pun pada card langsung buka panel, tombol tetap ada buat
                klik manual/mobile (`open` dipakai bersama, cuma warisan scope Alpine dari card). --}}
                <div style="position:relative;">
                    <button
                        type="button"
                        @click="open = !open"
                        style="display:inline-flex;align-items:center;gap:3px;flex-shrink:0;background:var(--surface-alt);border:1px solid var(--border-soft);border-radius:6px;padding:{{ $compact ? '2px 6px' : '3px 8px' }};font-size:{{ $compact ? '8.5px' : '10.5px' }};line-height:1.4;font-weight:600;color:var(--text-muted);cursor:pointer;white-space:nowrap;"
                    >
                        <i class="fa-solid fa-circle-info"></i> Detail
                    </button>

                    <div
                        x-show="open"
                        x-transition.opacity.duration.150ms
                        style="display:none;position:absolute;top:calc(100% + 6px);left:0;z-index:30;width:220px;background:#ffffff;border:1px solid #e2e8f0;border-radius:12px;box-shadow:0 4px 16px rgba(0,0,0,0.16);padding:12px 14px;"
                    >
                        <p style="font-size:12px;font-weight:700;color:#1e293b;margin-bottom:8px;">Detail Perhitungan</p>
                        <div style="display:flex;justify-content:space-between;align-items:center;font-size:11.5px;color:#64748b;margin-bottom:6px;">
                            <span style="display:flex;align-items:center;gap:6px;">
                                <span style="width:7px;height:7px;border-radius:50%;background:#94a3b8;flex-shrink:0;"></span>
                                Kuota
                            </span>
                            <span>{{ $balance['quota'] }} hari</span>
                        </div>
                        <div style="display:flex;justify-content:space-between;align-items:center;font-size:11.5px;margin-bottom:6px;">
                            <span style="display:flex;align-items:center;gap:6px;color:#64748b;">
                                <span style="width:7px;height:7px;border-radius:50%;background:#d97706;flex-shrink:0;"></span>
                                Cuti Bersama
                            </span>
                            <span style="color:#d97706;font-weight:600;">&minus; {{ $collectiveLeaveDays }} hari</span>
                        </div>
                        <div style="display:flex;justify-content:space-between;align-items:center;font-size:11.5px;margin-bottom:8px;">
                            <span style="display:flex;align-items:center;gap:6px;color:#64748b;">
                                <span style="width:7px;height:7px;border-radius:50%;background:#e11d48;flex-shrink:0;"></span>
                                Digunakan
                            </span>
                            <span style="color:#e11d48;font-weight:600;">&minus; {{ $balance['used'] }} hari</span>
                        </div>
                        <hr style="border:none;border-top:1px solid #e2e8f0;margin-bottom:8px;">
                        <div style="display:flex;justify-content:space-between;font-size:12.5px;font-weight:700;color:#1e293b;">
                            <span>Sisa</span><span>{{ $balance['available'] }} hari</span>
                        </div>
                    </div>
                </div>
            </div>

            @unless ($compact)
                <div class="progress-track"><div class="progress-fill" style="width:{{ $pct }}%;background:{{ $color }};"></div></div>
                <p style="font-size:11px;color:var(--text-muted);margin-top:5px;text-align:right;">dari kuota {{ $balance['quota'] }} hari &middot; {{ $balance['used'] }} digunakan</p>
            @endunless
        </div>
    @else
        <p style="font-size:{{ $compact ? '10px' : '13px' }};color:var(--text-muted);">Data tidak tersedia</p>
    @endif
</div>
