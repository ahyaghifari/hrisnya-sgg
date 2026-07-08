<div class="{{ $requests->isNotEmpty() ? 'bento-pending-approvals' : '' }}" @if ($requests->isEmpty()) style="display:none;" @endif>
@if ($requests->isNotEmpty())
    {{-- Identitas "Approval Dark": dipakai konsisten di semua kartu domain persetujuan
         (kartu ini + timeline status) supaya user langsung kenali "ini soal approval"
         di mana pun kartunya muncul (Dashboard, MyStaff, dsb), tanpa perlu baca teks. --}}
    <div style="background:#fff;border-radius:20px;box-shadow:0 2px 10px rgba(0,0,0,0.08);overflow:hidden;">
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

        <!-- MOBILE: card manual -->
        <div class="md:hidden">
            @php
                $typeLabel = fn (string $type) => ['leave' => 'Cuti', 'permission' => 'Izin', 'overtime' => 'Lembur'][$type] ?? $type;
                $typeIcon = fn (string $type) => ['leave' => 'fa-umbrella-beach', 'permission' => 'fa-clock', 'overtime' => 'fa-briefcase-clock'][$type] ?? 'fa-file-lines';
                $typeTile = fn (string $type) => ['leave' => 'neutral', 'permission' => 'warn', 'overtime' => 'done'][$type] ?? 'neutral';
            @endphp
            @foreach ($requests as $request)
                <a href="{{ route('my-attendance-requests.show', ['record' => $request->id]) }}" wire:navigate class="pwa-list-row" style="text-decoration:none;">
                    <div class="pwa-tile-icon {{ $typeTile($request->type) }}"><i class="fa-solid {{ $typeIcon($request->type) }}"></i></div>
                    <div style="flex:1;min-width:0;">
                        <p style="font-size:13px;font-weight:600;color:#1e293b;">{{ $typeLabel($request->type) }} · {{ $request->user->name }}</p>
                        <p style="font-size:11px;color:#64748b;margin-top:2px;">{{ $request->created_at->translatedFormat('d M Y') }}</p>
                    </div>
                    <i class="fa-solid fa-chevron-right"></i>
                </a>
            @endforeach
        </div>

        <!-- DESKTOP: table -->
        <div class="hidden md:block" style="overflow-x:auto;">
            <table class="data-table">
                <thead>
                    <tr><th>Jenis</th><th>Pemohon</th><th>Tanggal Pengajuan</th><th></th></tr>
                </thead>
                <tbody>
                    @foreach ($requests as $request)
                        <tr>
                            <td class="text-sm font-semibold text-slate-800">
                                {{ ['leave' => 'Cuti', 'permission' => 'Izin', 'overtime' => 'Lembur'][$request->type] ?? $request->type }}
                            </td>
                            <td class="text-sm text-slate-600">{{ $request->user->name }}</td>
                            <td class="text-sm text-slate-500 whitespace-nowrap">{{ $request->created_at->translatedFormat('d M Y') }}</td>
                            <td>
                                <a href="{{ route('my-attendance-requests.show', ['record' => $request->id]) }}" wire:navigate
                                    style="font-size:12.5px;font-weight:600;color:#042c6c;text-decoration:none;white-space:nowrap;">
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
