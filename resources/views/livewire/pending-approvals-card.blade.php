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
            $typeLabel = fn (string $type) => ['leave' => 'Cuti', 'permission' => 'Izin', 'overtime' => 'Lembur'][$type] ?? $type;
            $typeIcon = fn (string $type) => ['leave' => 'fa-umbrella-beach', 'permission' => 'fa-clock', 'overtime' => 'fa-briefcase-clock'][$type] ?? 'fa-file-lines';
            $typeTile = fn (string $type) => ['leave' => 'neutral', 'permission' => 'warn', 'overtime' => 'done'][$type] ?? 'neutral';
            // Keterangan: jenis SPESIFIK di dalam kategori (mis. "Cuti Tahunan", bukan cuma
            // "Cuti") — sekaligus kolom ini yang jadi tempat konteks buat modul approval lain
            // (bukan cuma attendance_request) kalau nanti kartu ini dipakai lintas modul, supaya
            // approver tidak cuma lihat "Cuti/Izin/Lembur" tanpa tahu detailnya apa.
            $keterangan = fn ($request) => match ($request->type) {
                'leave' => $request->leaveDetails->first()?->leave_type ?? '-',
                'permission' => $request->permissionDetails->first()?->permission_type ?? '-',
                'overtime' => $request->overtimeDetails->first()?->overtime_type ?? '-',
                default => '-',
            };
        @endphp

        <!-- MOBILE: card manual -->
        <div class="md:hidden">
            @foreach ($requests as $request)
                <a href="{{ route('my-attendance-requests.show', ['record' => $request->id]) }}" wire:navigate class="pwa-list-row" style="text-decoration:none;">
                    <div class="pwa-tile-icon {{ $typeTile($request->type) }}"><i class="fa-solid {{ $typeIcon($request->type) }}"></i></div>
                    <div style="flex:1;min-width:0;">
                        <p style="font-size:13px;font-weight:600;color:var(--text-heading);">{{ $typeLabel($request->type) }} · {{ $request->user->name }}</p>
                        <p style="font-size:11px;color:var(--text-muted);margin-top:2px;">{{ $keterangan($request) }} &middot; {{ $request->created_at->translatedFormat('d M Y') }}</p>
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
                            <td style="padding:9px 14px;font-size:12px;font-weight:600;color:var(--text-heading);white-space:nowrap;">
                                {{ $typeLabel($request->type) }}
                            </td>
                            <td style="padding:9px 14px;font-size:11.5px;color:var(--text-body);">{{ $keterangan($request) }}</td>
                            <td style="padding:9px 14px;font-size:11.5px;color:var(--text-muted);">{{ $request->user->name }}</td>
                            <td style="padding:9px 14px;font-size:11.5px;color:var(--text-faint);white-space:nowrap;">{{ $request->created_at->translatedFormat('d M Y') }}</td>
                            <td style="padding:9px 14px;">
                                <a href="{{ route('my-attendance-requests.show', ['record' => $request->id]) }}" wire:navigate
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
