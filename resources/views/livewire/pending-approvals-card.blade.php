<div class="{{ $requests->isNotEmpty() ? 'bento-pending-approvals' : '' }}" @if ($requests->isEmpty()) style="display:none;" @endif>
@if ($requests->isNotEmpty())
    {{-- Identitas "Approval Dark": dipakai konsisten di semua kartu domain persetujuan
         (kartu ini + timeline status) supaya user langsung kenali "ini soal approval"
         di mana pun kartunya muncul (Dashboard, MyStaff, dsb), tanpa perlu baca teks. --}}
    <div style="background:var(--surface);border-radius:20px;box-shadow:0 2px 10px rgba(0,0,0,0.08);overflow:hidden;">
        <div class="approval-card" style="padding:16px 20px;display:flex;align-items:center;justify-content:space-between;gap:12px;">
            <div class="appr-deco-1"></div><div class="appr-deco-2"></div>
            <div style="position:relative;z-index:1;display:flex;align-items:center;gap:12px;min-width:0;">
                <div style="flex-shrink:0;width:38px;height:38px;border-radius:11px;background:rgba(255,255,255,0.14);display:flex;align-items:center;justify-content:center;">
                    <i class="fa-solid fa-clipboard-check" style="color:#fff;font-size:15px;"></i>
                </div>
                <div style="min-width:0;">
                    <h3 style="font-size:14px;font-weight:800;color:#fff;margin:0;">Butuh Persetujuan Anda</h3>
                    <p style="font-size:11.5px;font-weight:600;color:rgba(255,255,255,0.7);margin-top:1px;">Menunggu tindakan Anda</p>
                </div>
            </div>
            <span style="position:relative;z-index:1;flex-shrink:0;background:#fff;color:#9a3412;font-size:13px;font-weight:800;min-width:28px;height:28px;padding:0 9px;border-radius:99px;display:flex;align-items:center;justify-content:center;">
                {{ $requests->count() }}
            </span>
        </div>

        @php
            // Badge warna beda per modul - kuning buat Izin/Cuti/Lembur (attendance_request),
            // biru sistem buat Perubahan Status Karyawan (user_transition), ungu buat Pelatihan
            // (lms_course_enrollment) - biar approver bisa langsung bedakan tanpa baca teks,
            // kartu ini gabungan lintas-modul.
            $badgeStyle = fn (string $badge) => match ($badge) {
                'blue' => 'background:rgba(4,44,108,0.1);color:#042c6c;',
                'purple' => 'background:rgba(126,34,206,0.1);color:#7e22ce;',
                default => 'background:rgba(217,119,6,0.12);color:#92400e;',
            };
        @endphp

        <!-- MOBILE: card manual -->
        <div class="md:hidden">
            @foreach ($requests as $request)
                <a href="{{ $request->url }}" @unless ($request->external) wire:navigate @endunless class="pwa-list-row" style="text-decoration:none;">
                    <div class="pwa-tile-icon {{ $request->tile }}"><i class="fa-solid {{ $request->icon }}"></i></div>
                    <div style="flex:1;min-width:0;">
                        <p style="font-size:13px;font-weight:600;color:var(--text-heading);">
                            <span style="display:inline-block;padding:2px 8px;border-radius:99px;font-size:10px;font-weight:700;{{ $badgeStyle($request->badge) }}">{{ $request->label }}</span>
                            {{ $request->user_name }}
                        </p>
                        <p style="font-size:11px;color:var(--text-muted);margin-top:2px;">{{ $request->keterangan }} &middot; {{ $request->created_at->translatedFormat('d M Y') }}</p>
                    </div>
                    <i class="fa-solid fa-chevron-right"></i>
                </a>
            @endforeach
        </div>

        <!-- DESKTOP: table (font/padding lebih rapat daripada .data-table default — kartu ini
        sengaja lebih padat karena bisa banyak baris menumpuk di dashboard) -->
        <div class="hidden md:block" style="overflow-x:auto;">
            <table class="data-table" style="min-width:560px;">
                <thead>
                    <tr>
                        <th style="padding:8px 14px;font-size:10px;">Jenis</th>
                        <th style="padding:8px 14px;font-size:10px;">Keterangan</th>
                        <th style="padding:8px 14px;font-size:10px;">Pemohon</th>
                        <th style="padding:8px 14px;font-size:10px;">Tanggal Pengajuan</th>
                        <th style="padding:8px 14px;font-size:10px;"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($requests as $request)
                        <tr>
                            <td style="padding:9px 14px;white-space:nowrap;">
                                <span style="display:inline-block;padding:3px 10px;border-radius:99px;font-size:11px;font-weight:700;{{ $badgeStyle($request->badge) }}">{{ $request->label }}</span>
                            </td>
                            <td style="padding:9px 14px;font-size:11.5px;color:var(--text-body);">{{ $request->keterangan }}</td>
                            <td style="padding:9px 14px;font-size:11.5px;color:var(--text-muted);">{{ $request->user_name }}</td>
                            <td style="padding:9px 14px;font-size:11.5px;color:var(--text-faint);white-space:nowrap;">{{ $request->created_at->translatedFormat('d M Y') }}</td>
                            <td style="padding:9px 14px;">
                                <a href="{{ $request->url }}" @unless ($request->external) wire:navigate @endunless
                                    style="font-size:11.5px;font-weight:600;color:var(--brand);text-decoration:none;white-space:nowrap;">
                                    Tinjau <i class="fa-solid fa-arrow-right text-xs"></i>
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif
</div>
